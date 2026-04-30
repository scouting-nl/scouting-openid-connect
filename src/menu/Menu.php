<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * This class manages the main menu page for the Scouting OIDC plugin, including registering the menu and its submenus.
 */
class Menu
{
    /**
     * Register the main menu page for the plugin.
     *
     * @return void
     */
    public function scouting_oidc_menu(): void {
        add_menu_page(
            'Scouting OIDC',          // Page title
            'Scouting OIDC',          // Menu title
            'manage_options',         // Capability
            'scouting-oidc-settings', // Menu slug
            '',                       // Callback function (none)
            'dashicons-admin-network' // Icon URL
        );
    }
}
?>