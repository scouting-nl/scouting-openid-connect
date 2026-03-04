<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . '../../src/utilities/Logger.php';

use ScoutingOIDC\Logger;

class Logging
{
    /** Register the logging page in the admin menu
     * 
     * @return void
     */
    public function scouting_oidc_logging_submenu_page(): void {
        add_submenu_page(
            'scouting-oidc-settings',                       // Parent slug (matches the main menu slug)
            'Logging',                                      // Page title
            'Logging',                                      // Menu title
            'manage_options',                               // Capability
            'scouting-oidc-logging',                        // Submenu slug
            [$this, 'scouting_oidc_logging_page_callback'], // Callback function
            4                                               // Menu position
        );
    }
}