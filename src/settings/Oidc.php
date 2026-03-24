<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * This class manages the OpenID Connect settings section of the Scouting OIDC plugin, including rendering the settings fields and sanitizing input values.
 */
class Settings_Oidc
{
    /**
     * Register OpenID Connect settings, sections, and fields.
     * 
     * @return void
     */
    public function scouting_oidc_settings_oidc(): void {
        // Add settings sections
        add_settings_section(
            'scouting_oidc_settings',                                 // ID
            __("OpenID Connect Settings", "scouting-openid-connect"), // Title
            [$this, 'scouting_oidc_settings_oidc_callback'],          // Callback to render section
            'scouting-openid-connect-settings'                        // Page slug where the section should be added
        );
    
        // Add a settings text field
        add_settings_field(
            'scouting_oidc_client_id',                                 // Field ID
            __("Client ID", "scouting-openid-connect"),                // Field label
            [$this, 'scouting_oidc_settings_oidc_client_id_callback'], // Callback to render field
            'scouting-openid-connect-settings',                        // Page slug
            'scouting_oidc_settings'                                   // Section ID where the field should be added
        );
    
        // Add a settings text field
        add_settings_field(
            'scouting_oidc_client_secret',                                 // Field ID
            __("Client Secret", "scouting-openid-connect"),                // Field label
            [$this, 'scouting_oidc_settings_oidc_client_secret_callback'], // Callback to render field
            'scouting-openid-connect-settings',                            // Page slug
            'scouting_oidc_settings'                                       // Section ID where the field should be added
        );
    
        // Add a settings text field
        add_settings_field(
            'scouting_oidc_scopes',                                 // Field ID
            __("Scopes", "scouting-openid-connect"),                // Field label
            [$this, 'scouting_oidc_settings_oidc_scopes_callback'], // Callback to render field
            'scouting-openid-connect-settings',                     // Page slug
            'scouting_oidc_settings'                                // Section ID where the field should be added
        );
    
        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                  // Settings group name
            'scouting_oidc_client_id',                       // Option name
            [
                'sanitize_callback' => 'sanitize_text_field' // Sanitize the input value as a text field
            ]
        );
    
        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                  // Settings group name
            'scouting_oidc_client_secret',                   // Option name
            [
                'sanitize_callback' => [$this, 'scouting_oidc_sanitize_client_secret_option'] // Keep existing secret when field is left blank
            ]
        );
    
        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                  // Settings group name
            'scouting_oidc_scopes',                          // Option name
            [
                'sanitize_callback' => 'sanitize_text_field' // Sanitize the input value as a text field
            ]
        );
    }

    /**
     * Callback to render section content.
     *
     * @return void
     */
    public function scouting_oidc_settings_oidc_callback(): void {}

    /**
     * Callback to render text field.
     *
     * @return void
     */
    public function scouting_oidc_settings_oidc_client_id_callback(): void {
        $value = get_option('scouting_oidc_client_id');
        echo '<input type="text" id="scouting_oidc_client_id" name="scouting_oidc_client_id" placeholder="'. esc_attr__("Client ID", "scouting-openid-connect") .'" value="' . esc_attr($value) . '" size="55" required>';
    }

    /**
     * Callback to render text field.
     *
     * @return void
     */
    public function scouting_oidc_settings_oidc_client_secret_callback(): void {
        $has_secret = get_option('scouting_oidc_client_secret') !== '';
        $input_id = 'scouting_oidc_client_secret';
        $toggle_id = 'scouting_oidc_client_secret_toggle';
        $show_text = __('Show', 'scouting-openid-connect');
        $hide_text = __('Hide', 'scouting-openid-connect');

        echo '<input type="password" id="' . esc_attr($input_id) . '" name="scouting_oidc_client_secret" placeholder="' . esc_attr__("Enter new client secret", "scouting-openid-connect") . '" value="" size="55" autocomplete="new-password" />';
        echo ' <button type="button" class="button" id="' . esc_attr($toggle_id) . '" data-show-text="' . esc_attr($show_text) . '" data-hide-text="' . esc_attr($hide_text) . '" disabled>' . esc_html($show_text) . '</button>';
        if ($has_secret) {
            echo '<p class="description">' . esc_html__("A client secret is already stored. Leave this field empty to keep the current secret. For security reasons, the stored value cannot be shown here.", "scouting-openid-connect") . '</p>';
        } else {
            echo '<p class="description">' . esc_html__("No client secret stored yet.", "scouting-openid-connect") . '</p>';
        }
    }

    /**
     * Sanitize client secret while preserving the existing value when blank.
     *
     * @param mixed $input
     * @return string
     */
    public function scouting_oidc_sanitize_client_secret_option(mixed $input): string {
        $input = is_string($input) ? trim($input) : '';

        if ($input === '') {
            $existing = get_option('scouting_oidc_client_secret', '');
            return is_string($existing) ? $existing : '';
        }

        return sanitize_text_field($input);
    }

    /**
     * Callback to render text field.
     *
     * @return void
     */
    public function scouting_oidc_settings_oidc_scopes_callback(): void {
        $value = get_option('scouting_oidc_scopes');
        echo '<input type="text" id="scouting_oidc_scopes" name="scouting_oidc_scopes" placeholder="'. esc_attr__("Scopes", "scouting-openid-connect") .'" value="' . esc_attr($value) . '" size="55" required>';
    }
}
?>