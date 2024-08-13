<?php

include_once 'oidc.php';
include_once 'general.php';

// Add the OpenID Connect settings page to the admin menu
function scouting_oidc_settings_page_init() {
    add_oidc_settings();
    add_general_settings();
}

// Set up defaults during installation
function scouting_oidc_settings_install() {
	// set default options for OIDC
	update_option('scouting_oidc_client_id', '');
	update_option('scouting_oidc_client_secret', '');
	update_option('scouting_oidc_scopes', 'openid email membership profile');

    // set default options for general settings
	update_option('scouting_oidc_user_display_name', 'fullname');
	update_option('scouting_oidc_user_birthdate', false);
	update_option('scouting_oidc_user_gender', false);
	update_option('scouting_oidc_user_scouting_id', false);
	update_option('scouting_oidc_user_name_prefix', 'sn_');
	update_option('scouting_oidc_user_auto_create', true);
	update_option('scouting_oidc_login_redirect', 'frontpage');
}

// Callback to render settings page content
function scouting_oidc_settings_page_callback() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Scouting OpenID Connect Settings', 'scouting-openid-connect'); ?></h1>
        <?php settings_errors(); ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('scouting_oidc_settings_group'); // Settings group name
            do_settings_sections('scouting-openid-connect-settings'); // Page slug
            submit_button('Save Settings');
            ?>
        </form>
    </div>
    <?php
}
?>