<?php
function add_oidc_settings() {
    // Add settings sections
    add_settings_section(
        'scouting_oidc_settings',                                 // ID
        __("OpenID Connect Settings", "scouting-openid-connect"), // Title
        'scouting_oidc_settings_callback',                        // Callback to render section
        'scouting-openid-connect-settings'                        // Page slug where the section should be added
    );

    // Add a settings text field
    add_settings_field(
        'scouting_oidc_client_id',                  // Field ID
        __("Client ID", "scouting-openid-connect"), // Field label
        'scouting_oidc_client_id_callback',         // Callback to render field
        'scouting-openid-connect-settings',         // Page slug
        'scouting_oidc_settings'                    // Section ID where the field should be added
    );

    // Add a settings text field
    add_settings_field(
        'scouting_oidc_client_secret',                  // Field ID
        __("Client Secret", "scouting-openid-connect"), // Field label
        'scouting_oidc_client_secret_callback',         // Callback to render field
        'scouting-openid-connect-settings',             // Page slug
        'scouting_oidc_settings'                        // Section ID where the field should be added
    );

    // Add a settings text field
    add_settings_field(
        'scouting_oidc_scopes',                  // Field ID
        __("Scopes", "scouting-openid-connect"), // Field label
        'scouting_oidc_scopes_callback',         // Callback to render field
        'scouting-openid-connect-settings',      // Page slug
        'scouting_oidc_settings'                 // Section ID where the field should be added
    );

    // Register settings
    register_setting(
        'scouting_oidc_settings_group', // Settings group name
        'scouting_oidc_client_id'       // Option name
    );

    // Register settings
    register_setting(
        'scouting_oidc_settings_group', // Settings group name
        'scouting_oidc_client_secret'   // Option name
    );

    // Register settings
    register_setting(
        'scouting_oidc_settings_group', // Settings group name
        'scouting_oidc_scopes'          // Option name
    );
}


// Callback to render section content
function scouting_oidc_settings_callback() {}

// Callback to render text field
function scouting_oidc_client_id_callback() {
    $value = get_option('scouting_oidc_client_id');
    echo '<input type="text" id="scouting_oidc_client_id" name="scouting_oidc_client_id" placeholder="'. esc_attr__("Client ID", "scouting-openid-connect") .'" value="' . esc_attr($value) . '" size="50" required>';
}

// Callback to render text field
function scouting_oidc_client_secret_callback() {
    $value = get_option('scouting_oidc_client_secret');
    echo '<input type="text" id="scouting_oidc_client_secret" name="scouting_oidc_client_secret" placeholder="'. esc_attr__("Client Secret", "scouting-openid-connect") .'" value="' . esc_attr($value) . '" size="150" required>';
}

// Callback to render text field
function scouting_oidc_scopes_callback() {
    $value = get_option('scouting_oidc_scopes');
    echo '<input type="text" id="scouting_oidc_scopes" name="scouting_oidc_scopes" placeholder="'. esc_attr__("Scopes", "scouting-openid-connect") .'" value="' . esc_attr($value) . '" size="50" required>';
}
?>