<?php namespace UltimatePushNotifications\front\bp;

/**
 * Class: Admin Menu Scripts
 *
 * @package Admin
 * @since 1.0.0
 * @author M.Tuhin <info@codesolz.net>
 */

if ( ! defined( 'CS_UPN_VERSION' ) ) {
	exit;
}


class BpSettingsTpl {

    public function __construct(){
        global $bp;

		add_action( 'bp_template_title', array( $this, 'upn_bp_settings_top_points_events_title' ) );
		add_action( 'bp_template_content', array( $this, 'upn_bp_settings_top_points_events_content' ) );
		bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
		//bp_core_load_template( apply_filters( 'bp_em_template_screen_one', 'em/screen-one' ) );
    }

    /**
     * Section Title
     *
     * @return void
     */
    public function upn_bp_settings_top_points_events_title(){
		_e( 'Settings', 'ultimate-push-notifications');
	}

	public function upn_bp_settings_top_points_events_content(){
		?>
        <form action="<?php echo trailingslashit( bp_displayed_user_domain() . $bp->notifications->slug ) .'/push-notifications'; ?>" method="post" id="profile-edit-form" class="standard-form profile-edit base">
	
            <h3 class="screen-heading profile-group-title edit">Editing "Base" Profile Group</h3>
                <div class="editfield field_1 field_name required-field visibility-public field_type_textbox">
                    <fieldset>
                    
            <legend id="field_1-1"> Name<span class="bp-required-field-label">(required)</span></legend>


            <input id="field_1" name="field_1" type="text" value="" aria-required="true" aria-labelledby="field_1-1" aria-describedby="field_1-3">



                    

            <p class="field-visibility-settings-notoggle field-visibility-settings-header" id="field-visibility-settings-toggle-1">
            This field may be seen by: <span class="current-visibility-level">Everyone</span>	</p>


                    </fieldset>
                </div>



            <input type="hidden" name="field_ids" id="field_ids" value="1">

            <div class="submit"><input type="submit" name="profile-group-edit-submit" id="profile-group-edit-submit" value="Save Changes"></div><input type="hidden" id="_wpnonce" name="_wpnonce" value="89eb0f1289"><input type="hidden" name="_wp_http_referer" value="/members/admin/profile/edit/group/1/">
            </form>
        <?php
	}

}