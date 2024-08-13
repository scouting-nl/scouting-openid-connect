<?php
// Add the OpenID Connect settings page to the admin menu
function scouting_oidc_menu() {
    add_menu_page(
        'Scouting OIDC',  // Page title
        'Scouting OIDC',  // Menu title
        'manage_options', // Capability
        'scouting-oidc-settings', // Menu slug
        'scouting_oidc_settings_page_callback', // Callback function
        'dashicons-admin-network' // Icon URL
   );
}
?>