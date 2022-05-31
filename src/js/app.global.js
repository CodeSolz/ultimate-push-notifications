jQuery( document ).ready(
	function($){
		$( '.cs-notice' ).on(
			'click',
			'.notice-dismiss',
			function( event, el ) {
				var $notice     = $( this ).parent( '.notice.is-dismissible' );
				var dismiss_url = $notice.attr( 'data-dismiss-url' );
				if ( dismiss_url ) {
					$.get( dismiss_url );
				}

				console.log('msg dismissed');
			}
		);
	}
);
