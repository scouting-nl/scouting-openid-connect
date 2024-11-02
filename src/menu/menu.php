<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Menu
{
    // Add the OpenID Connect settings page to the admin menu
    public function scouting_oidc_menu() {
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