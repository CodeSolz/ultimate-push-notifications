/**
 * Ultimate Push Notifications — Web Push client.
 *
 * Plain script, no build step, no external SDK. Registers the root-scope
 * service worker on every page, but only asks for notification permission
 * when the user does something that means "yes": visiting Register My Device,
 * or clicking an element carrying data-upn-subscribe. Prompting cold on the
 * first page view burns the permission for good and trips Chrome's quiet-UI
 * heuristics, which is what the previous implementation did.
 *
 * Exposes window.UPN_WebPush.subscribe() / .unsubscribe() / .status() for
 * themes and other plugins.
 */
(function (window, document) {
	'use strict';

	var cfg = window.UPN_WebPush || null;
	if (!cfg || !cfg.publicKey || !cfg.swUrl) {
		return;
	}

	var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
	// iPhone and iPad: push exists only once the site is on the Home Screen (iOS 16.4+), so an ordinary tab cannot subscribe.
	var isIOS = /iP(hone|ad|od)/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
	var standalone = window.navigator.standalone === true || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
	var needsInstall = isIOS && !standalone && !supported;

	function urlBase64ToUint8Array(base64String) {
		var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
		var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
		var raw = window.atob(base64);
		var out = new Uint8Array(raw.length);
		for (var i = 0; i < raw.length; ++i) { out[i] = raw.charCodeAt(i); }
		return out;
	}

	function post(method, fields) {
		var body = new FormData();
		body.append('action', 'upn_ajax');
		body.append('cs_token', cfg.nonce);
		body.append('method', method);
		Object.keys(fields || {}).forEach(function (k) { body.append(k, fields[k]); });
		return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); });
	}

	function notify(json) {
		if (window.Swal && typeof window.Swal.fire === 'function') {
			window.Swal.fire({ title: json.title || '', text: json.text || '', icon: json.status ? 'success' : 'error', timer: 5000 });
		} else if (window.console) {
			(json.status ? console.log : console.warn)('[UPN] ' + (json.title || '') + ' ' + (json.text || ''));
		}
		document.dispatchEvent(new CustomEvent('upn:subscription', { detail: json }));
	}

	function register() {
		return navigator.serviceWorker.register(cfg.swUrl, { scope: '/' });
	}

	function saveToServer(subscription) {
		var json = subscription.toJSON();
		var tz = '';
		try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
		return post(cfg.saveMethod, {
			endpoint: json.endpoint,
			p256dh: json.keys && json.keys.p256dh ? json.keys.p256dh : '',
			auth: json.keys && json.keys.auth ? json.keys.auth : '',
			locale: navigator.language || '',
			timezone: tz,
			source: window.location.href
		});
	}

	/**
	 * Ask for permission (if needed), subscribe, and record it on the server.
	 * Must be called from a user gesture to show the native prompt reliably.
	 */
	function subscribe() {
		if (!supported) {
			var unsupported = { status: false, title: cfg.i18n.unsupportedTitle, text: cfg.i18n.unsupportedText };
			notify(unsupported);
			return Promise.resolve(unsupported);
		}
		if (!cfg.loggedIn && !cfg.allowAnonymous) {
			var login = { status: false, title: cfg.i18n.loginTitle, text: cfg.i18n.loginText };
			notify(login);
			return Promise.resolve(login);
		}

		return register().then(function (registration) {
			return Notification.requestPermission().then(function (permission) {
				if (permission !== 'granted') {
					var denied = { status: false, title: cfg.i18n.deniedTitle, text: cfg.i18n.deniedText };
					notify(denied);
					return denied;
				}
				return registration.pushManager.getSubscription().then(function (existing) {
					if (existing) { return existing; }
					return registration.pushManager.subscribe({
						userVisibleOnly: true,
						applicationServerKey: urlBase64ToUint8Array(cfg.publicKey)
					});
				}).then(saveToServer).then(function (json) {
					notify(json);
					return json;
				});
			});
		}).catch(function (err) {
			var failed = { status: false, title: cfg.i18n.errorTitle, text: (err && err.message) ? err.message : String(err) };
			notify(failed);
			return failed;
		});
	}

	/**
	 * Unsubscribe this browser and tell the server.
	 */
	function unsubscribe() {
		if (!supported) { return Promise.resolve({ status: false }); }
		return navigator.serviceWorker.getRegistration('/').then(function (registration) {
			if (!registration) { return { status: true, title: '', text: '' }; }
			return registration.pushManager.getSubscription().then(function (subscription) {
				if (!subscription) { return { status: true, title: '', text: '' }; }
				var endpoint = subscription.endpoint;
				return subscription.unsubscribe().then(function () {
					return post(cfg.removeMethod, { endpoint: endpoint });
				});
			});
		}).then(function (json) { notify(json); return json; });
	}

	/**
	 * What state is this browser in? Resolves { supported, permission, subscribed }.
	 */
	function status() {
		if (!supported) { return Promise.resolve({ supported: false, permission: 'unsupported', subscribed: false }); }
		return navigator.serviceWorker.getRegistration('/').then(function (registration) {
			if (!registration) { return { supported: true, permission: Notification.permission, subscribed: false }; }
			return registration.pushManager.getSubscription().then(function (subscription) {
				return { supported: true, permission: Notification.permission, subscribed: !!subscription };
			});
		});
	}

	/*
	 * Silent sync on every page load: if this browser already holds a
	 * subscription, re-report it. Keeps last_seen fresh and reassigns the
	 * device when a different user logs in on the same browser. Never prompts.
	 */
	function syncExisting() {
		if (!supported || Notification.permission !== 'granted') { return; }
		if (!cfg.loggedIn && !cfg.allowAnonymous) { return; }
		register().then(function (registration) {
			return registration.pushManager.getSubscription();
		}).then(function (subscription) {
			if (subscription) { return saveToServer(subscription); }
		}).catch(function () {});
	}

	/* ------------------------------------------------------------------
	 * Opt-in surfaces: soft-ask prompt, floating bell, subscribe buttons.
	 * ---------------------------------------------------------------- */

	var optin = cfg.optin || {};

	function storage(key, value) {
		try {
			if (arguments.length === 1) { return window.localStorage.getItem(key); }
			window.localStorage.setItem(key, value);
		} catch (e) { return null; }
	}

	/** Update every subscribe button and the bell to reflect the real state. */
	function reflectState() {
		status().then(function (s) {
			var on = s.subscribed;
			document.querySelectorAll('[data-upn-subscribe]').forEach(function (b) {
				var label = on ? b.getAttribute('data-upn-label-on') : b.getAttribute('data-upn-label-off');
				if (label) { b.textContent = label; }
				b.setAttribute('data-upn-state', on ? 'on' : 'off');
				if (on) { b.setAttribute('data-upn-unsubscribe', ''); } else { b.removeAttribute('data-upn-unsubscribe'); }
			});
			var bell = document.getElementById('upn-bell');
			if (bell) {
				bell.classList.toggle('upn-bell--on', on);
				bell.setAttribute('aria-label', on ? optin.bell.labelOn : optin.bell.labelOff);
				bell.title = on ? optin.bell.labelOn : optin.bell.labelOff;
			}
		});
	}

	function renderBell() {
		if (!optin.bell || !optin.bell.enabled || document.getElementById('upn-bell')) { return; }
		var b = document.createElement('button');
		b.id = 'upn-bell';
		b.type = 'button';
		b.className = 'upn-bell upn-bell--' + (optin.bell.position || 'bottom-right');
		b.style.background = optin.bell.color || '#2271b1';
		b.setAttribute('aria-label', optin.bell.labelOff);
		b.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>';
		b.addEventListener('click', function () {
			status().then(function (s) {
				// An extension (a preference centre) may take the click over: preventDefault() and nothing else happens.
				var ev = new CustomEvent('upn:bell-click', { cancelable: true, detail: s });
				if (!document.dispatchEvent(ev)) { return; }
				if (s.subscribed) { unsubscribe().then(reflectState); } else { subscribe().then(reflectState); }
			});
		});
		document.body.appendChild(b);
	}

	/** The soft-ask bar for an iPhone that has not added the site yet: how to, and a way to close it. */
	function renderInstallGuide() {
		if (document.getElementById('upn-prompt')) { return; }
		var p = optin.prompt, g = (cfg.pwa && cfg.pwa.ios) || {};
		var box = document.createElement('div');
		box.id = 'upn-prompt';
		box.className = 'upn-prompt upn-prompt--' + (p.position || 'bottom') + ' upn-prompt--install';
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-label', g.title || '');
		var icon = document.createElement('div'); icon.className = 'upn-prompt__icon'; icon.textContent = '📲';
		var body = document.createElement('div'); body.className = 'upn-prompt__body';
		var title = document.createElement('p'); title.className = 'upn-prompt__title'; title.textContent = g.title || '';
		var text = document.createElement('p'); text.className = 'upn-prompt__text'; text.textContent = g.text || '';
		var actions = document.createElement('div'); actions.className = 'upn-prompt__actions';
		var ok = document.createElement('button'); ok.type = 'button'; ok.className = 'upn-prompt__btn upn-prompt__btn--later'; ok.textContent = g.ok || 'OK';
		ok.addEventListener('click', function () {
			if (box.parentNode) { box.parentNode.removeChild(box); }
			storage('upn_prompt_until', String(Date.now() + (p.dismissDays || 7) * 86400000));
		});
		actions.appendChild(ok); body.appendChild(title); body.appendChild(text); body.appendChild(actions);
		box.appendChild(icon); box.appendChild(body);
		document.body.appendChild(box);
		document.dispatchEvent(new CustomEvent('upn:install-guide-shown'));
	}

	function renderPrompt() {
		if (document.getElementById('upn-prompt')) { return; }
		var p = optin.prompt;
		var box = document.createElement('div');
		box.id = 'upn-prompt';
		box.className = 'upn-prompt upn-prompt--' + (p.position || 'bottom');
		box.setAttribute('role', 'dialog');
		box.setAttribute('aria-live', 'polite');
		box.setAttribute('aria-label', p.title);

		var icon = document.createElement('div'); icon.className = 'upn-prompt__icon'; icon.textContent = '🔔';
		var body = document.createElement('div'); body.className = 'upn-prompt__body';
		var title = document.createElement('p'); title.className = 'upn-prompt__title'; title.textContent = p.title;
		var text = document.createElement('p'); text.className = 'upn-prompt__text'; text.textContent = p.text;
		var actions = document.createElement('div'); actions.className = 'upn-prompt__actions';
		var allow = document.createElement('button'); allow.type = 'button'; allow.className = 'upn-prompt__btn upn-prompt__btn--allow'; allow.textContent = p.allow;
		var later = document.createElement('button'); later.type = 'button'; later.className = 'upn-prompt__btn upn-prompt__btn--later'; later.textContent = p.later;

		function close() { if (box.parentNode) { box.parentNode.removeChild(box); } }

		allow.addEventListener('click', function () {
			close();
			// The click is the user gesture the native prompt needs.
			subscribe().then(reflectState);
		});
		later.addEventListener('click', function () {
			close();
			storage('upn_prompt_until', String(Date.now() + (p.dismissDays || 7) * 86400000));
		});

		actions.appendChild(allow); actions.appendChild(later);
		body.appendChild(title); body.appendChild(text); body.appendChild(actions);
		box.appendChild(icon); box.appendChild(body);
		document.body.appendChild(box);
		document.dispatchEvent(new CustomEvent('upn:prompt-shown'));
	}

	/**
	 * Decide whether to show the soft-ask, and when.
	 *
	 * Never shown when: unsupported, already subscribed, permission already
	 * decided either way, dismissed within the window, or the page-view
	 * threshold has not been reached. Then waits for the delay and, if set,
	 * for the scroll depth.
	 */
	function maybePrompt() {
		var p = optin.prompt;
		var guide = needsInstall && cfg.pwa && cfg.pwa.enabled;
		if (!p || !p.enabled || (!supported && !guide)) { return; }
		if (supported && Notification.permission !== 'default') { return; }

		var until = parseInt(storage('upn_prompt_until') || '0', 10);
		if (until && Date.now() < until) { return; }

		var views = parseInt(storage('upn_pv') || '0', 10) + 1;
		storage('upn_pv', String(views));
		if (views < (p.pageviews || 1)) { return; }

		status().then(function (s) {
			if (s.subscribed) { return; }

			var shown = false;
			function show() { if (shown) { return; } shown = true; if (guide) { renderInstallGuide(); } else { renderPrompt(); } }

			function armScroll() {
				if (!p.scroll) { show(); return; }
				function onScroll() {
					var h = document.documentElement;
					var pct = 100 * (window.scrollY || h.scrollTop) / Math.max(1, h.scrollHeight - h.clientHeight);
					if (pct >= p.scroll) { window.removeEventListener('scroll', onScroll); show(); }
				}
				window.addEventListener('scroll', onScroll, { passive: true });
				onScroll();
			}

			window.setTimeout(armScroll, (p.delay || 0) * 1000);
		});
	}

	window.UPN_WebPush = window.UPN_WebPush || {};
	window.UPN_WebPush.subscribe = subscribe;
	window.UPN_WebPush.unsubscribe = unsubscribe;
	window.UPN_WebPush.status = status;
	window.UPN_WebPush.supported = supported;
	window.UPN_WebPush.needsInstall = needsInstall;
	window.UPN_WebPush.refresh = reflectState;
	// For extensions: call an allow-listed handler, and learn this browser's endpoint (null when not subscribed).
	window.UPN_WebPush.post = post;
	window.UPN_WebPush.endpoint = function () {
		if (!supported) { return Promise.resolve(null); }
		return navigator.serviceWorker.getRegistration('/').then(function (registration) {
			return registration ? registration.pushManager.getSubscription() : null;
		}).then(function (subscription) { return subscription ? subscription.endpoint : null; }).catch(function () { return null; });
	};

	document.addEventListener('click', function (event) {
		var el = event.target.closest ? event.target.closest('[data-upn-subscribe],[data-upn-unsubscribe]') : null;
		if (!el) { return; }
		event.preventDefault();
		if (el.hasAttribute('data-upn-unsubscribe')) { unsubscribe().then(reflectState); } else { subscribe().then(reflectState); }
	});

	function onReady() {
		renderBell();
		reflectState();
		if (cfg.autoSubscribe) {
			// The user navigated to "Register My Device" — that is the gesture.
			subscribe().then(reflectState);
		} else {
			syncExisting();
			maybePrompt();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady);
	} else {
		onReady();
	}
})(window, document);
