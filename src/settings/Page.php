<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . 'Oidc.php';
require_once plugin_dir_path(__FILE__) . 'General.php';

use ScoutingOIDC\Settings_Oidc;
use ScoutingOIDC\Settings_General;

/**
 * This class manages the settings page for the Scouting OIDC plugin, including rendering the settings page, initializing settings sections and fields, and handling default options.
 */
class Settings
{
    /**
     * Register the settings submenu page under the main menu.
     *
     * @return void
     */
    public function scouting_oidc_settings_submenu_page(): void {
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

    /**
     * Callback function to render the settings page content.
     *
     * @return void
     */
    public function scouting_oidc_settings_page_callback(): void {
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

    /**
     * Initialize the settings page and register all settings sections and fields.
     *
     * @return void
     */
    public function scouting_oidc_settings_page_init(): void {
        $scouting_oidc_settings_oidc = new Settings_Oidc();
        $scouting_oidc_settings_oidc->scouting_oidc_settings_oidc();

        $scouting_oidc_settings_general = new Settings_General();
        $scouting_oidc_settings_general->scouting_oidc_settings_general();
    }
    
    /**
     * This script renders JavaScript that adds interactivity to the settings page, such as showing/hiding fields based on other field values.
     *
     * @return void
     */
    public function scouting_oidc_fields_enqueue_settings_script(): void {
        // Enqueue the external JavaScript file
        wp_enqueue_script(
            'scouting-oidc-settings-script',       // Handle name
            plugins_url('settings.js', __FILE__),  // Path to the file
            array(),                                // No dependencies
            SCOUTING_OIDC_VERSION,                  // Version number
            array(
                'strategy' => 'defer',              // Add the defer attribute
                'in_footer' => true                 // Load the script in the footer
            )
        );
    } 

    /**
     * Set default options upon plugin activation.
     *
     * @return void
     */
    public function scouting_oidc_settings_install(): void {
        // Set default options for OIDC
        add_option('scouting_oidc_client_id', '');
        add_option('scouting_oidc_client_secret', '');
        add_option('scouting_oidc_scopes', 'openid membership profile email address phone');

        // Set default options for general settings
        add_option('scouting_oidc_user_display_name', 'fullname');
        add_option('scouting_oidc_user_birthdate', false);
        add_option('scouting_oidc_user_gender', false);
        add_option('scouting_oidc_user_phone', false);
        add_option('scouting_oidc_user_address', false);
        add_option('scouting_oidc_user_woocommerce_sync', false);
        add_option('scouting_oidc_user_auto_create', true);
        add_option('scouting_oidc_user_duplicate_email', 'plus_addressing');
        add_option('scouting_oidc_user_redirect', true);
        add_option('scouting_oidc_login_redirect', 'frontpage');
        add_option('scouting_oidc_custom_redirect', '');
        add_option('scouting_oidc_debug_logging_enabled', false);
    }
}
?>