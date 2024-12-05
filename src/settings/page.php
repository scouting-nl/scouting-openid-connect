<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . 'oidc.php';
require_once plugin_dir_path(__FILE__) . 'general.php';

use ScoutingOIDC\Settings_Oidc;
use ScoutingOIDC\Settings_General;

class Settings
{
    public function scouting_oidc_settings_submenu_page() {
        add_submenu_page(
            'scouting-oidc-settings',                        // Parent slug (matches the main menu slug)
            'Settings',                                      // Page title
            'Settings',                                      // Menu title
            'manage_options',                                // Capability
            'scouting-oidc-settings',                        // Submenu slug
            [$this, 'scouting_oidc_settings_page_callback'], // Callback function
            1                                                // Menu position
        );
    }

    // Callback to render settings page content
    public function scouting_oidc_settings_page_callback() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Settings', 'scouting-openid-connect'); ?></h1>
            <p>
                <?php esc_html_e('Need help with setting up?', 'scouting-openid-connect'); ?> 
                <a href="<?php echo esc_url(admin_url('admin.php?page=scouting-oidc-support')); ?>"><?php esc_html_e('Go to the support page', 'scouting-openid-connect'); ?></a>.
            </p>
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

    // Add the OpenID Connect settings page to the admin menu
    public function scouting_oidc_settings_page_init() {
        $scouting_oidc_settings_oidc = new Settings_Oidc();
        $scouting_oidc_settings_oidc->scouting_oidc_settings_oidc();

        $scouting_oidc_settings_general = new Settings_General();
        $scouting_oidc_settings_general->scouting_oidc_settings_general();
    }

    // Set up defaults during installation
    public function scouting_oidc_settings_install() {
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
}
?>