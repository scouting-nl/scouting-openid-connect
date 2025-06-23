<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Settings_General
{
    public function scouting_oidc_settings_general() {
        // Add settings sections
        add_settings_section(
            'scouting_oidc_general_settings',                   // ID
            __('General Settings', 'scouting-openid-connect'),  // Title
            [$this, 'scouting_oidc_settings_general_callback'], // Callback to render section
            'scouting-openid-connect-settings'                  // Page slug where the section should be added
        );
    
        // Add a settings selectbox field
        add_settings_field(
            'scouting_oidc_user_display_name',                               // Field ID
            __('Set display name', 'scouting-openid-connect'),               // Field label
            [$this, 'scouting_oidc_settings_general_display_name_callback'], // Callback to render field
            'scouting-openid-connect-settings',                              // Page slug
            'scouting_oidc_general_settings'                                 // Section ID where the field should be added
        );
    
        // Add a settings checkbox field
        add_settings_field(
            'scouting_oidc_user_birthdate',                                    // Field ID
            __('Store birthdate to local profile', 'scouting-openid-connect'), // Field label
            [$this, 'scouting_oidc_settings_general_birthdate_callback'],      // Callback to render field
            'scouting-openid-connect-settings',                                // Page slug
            'scouting_oidc_general_settings'                                   // Section ID where the field should be added
        );
    
        // Add a settings checkbox field
        add_settings_field(
            'scouting_oidc_user_gender',                                    // Field ID
            __('Store gender to local profile', 'scouting-openid-connect'), // Field label
            [$this, 'scouting_oidc_settings_general_gender_callback'],      // Callback to render field
            'scouting-openid-connect-settings',                             // Page slug
            'scouting_oidc_general_settings'                                // Section ID where the field should be added
        );
    
        // Add a settings checkbox field
        add_settings_field(
            'scouting_oidc_user_scouting_id',                                    // Field ID
            __('Store Scouting ID to local profile', 'scouting-openid-connect'), // Field label
            [$this, 'scouting_oidc_settings_general_scouting_id_callback'],      // Callback to render field
            'scouting-openid-connect-settings',                                  // Page slug
            'scouting_oidc_general_settings'                                     // Section ID where the field should be added
        );
    
        // Add a settings checkbox field
        add_settings_field(
            'scouting_oidc_user_auto_create',                                    // Field ID
            __('Allow new user accounts', 'scouting-openid-connect'),            // Field label
            [$this, 'scouting_oidc_settings_general_user_auto_create_callback'], // Callback to render field
            'scouting-openid-connect-settings',                                  // Page slug
            'scouting_oidc_general_settings'                                     // Section ID where the field should be added
        );
        
        // Add a settings text field
        add_settings_field(
            'scouting_oidc_user_name_prefix',                                         // Field ID
            __('Prefix for all Scouting Nederland users', 'scouting-openid-connect'), // Field label
            [$this, 'scouting_oidc_settings_general_user_name_prefix_callback'],      // Callback to render field
            'scouting-openid-connect-settings',                                       // Page slug
            'scouting_oidc_general_settings'                                          // Section ID where the field should be added
        );

        // Add a settings checkbox field
        add_settings_field(
            'scouting_oidc_user_redirect',                                    // Field ID
            __('Redirect only SOL users', 'scouting-openid-connect'),         // Field label
            [$this, 'scouting_oidc_settings_general_user_redirect_callback'], // Callback to render field
            'scouting-openid-connect-settings',                               // Page slug
            'scouting_oidc_general_settings'                                  // Section ID where the field should be added
        );
    
        // Add a settings selectbox field
        add_settings_field(
            'scouting_oidc_login_redirect',                                             // Field ID
            __('After a successful login redirect user to', 'scouting-openid-connect'), // Field label
            [$this, 'scouting_oidc_settings_general_login_redirect_callback'],          // Callback to render field
            'scouting-openid-connect-settings',                                         // Page slug
            'scouting_oidc_general_settings'                                            // Section ID where the field should be added
        );
    
        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                                                  // Settings group name
            'scouting_oidc_user_display_name',                                               // Option name
            [
                'sanitize_callback' => [$this, 'scouting_oidc_sanitize_display_name_option'] // Sanitize the input value as display name
            ]
        );
    
        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                                             // Settings group name
            'scouting_oidc_user_birthdate',                                             // Option name
            [
                'sanitize_callback' => [$this, 'scouting_oidc_sanitize_boolean_option'] // Sanitize the input value as a boolean (0 or 1)
            ]
        );
    
        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                                             // Settings group name
            'scouting_oidc_user_gender',                                                // Option name
            [
                'sanitize_callback' => [$this, 'scouting_oidc_sanitize_boolean_option'] // Sanitize the input value as a boolean (0 or 1)
            ]
        );
    
        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                                             // Settings group name
            'scouting_oidc_user_scouting_id',                                           // Option name
            [
                'sanitize_callback' => [$this, 'scouting_oidc_sanitize_boolean_option'] // Sanitize the input value as a boolean (0 or 1)
            ]
        );
    
        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                                             // Settings group name
            'scouting_oidc_user_auto_create',                                           // Option name
            [
                'sanitize_callback' => [$this, 'scouting_oidc_sanitize_boolean_option'] // Sanitize the input value as a boolean (0 or 1)
            ]
        );
    
        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                  // Settings group name
            'scouting_oidc_user_name_prefix',                // Option name
            [
                'sanitize_callback' => 'sanitize_text_field' // Sanitize the input value as a text field
            ]
        );
        
        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                                             // Settings group name
            'scouting_oidc_user_redirect',                                              // Option name
            [
                'sanitize_callback' => [$this, 'scouting_oidc_sanitize_boolean_option'] // Sanitize the input value as a boolean (0 or 1)
            ]
        );

        // Register settings
        register_setting(
            'scouting_oidc_settings_group',                                                    // Settings group name
            'scouting_oidc_login_redirect',                                                    // Option name
            [
                'sanitize_callback' => [$this, 'scouting_oidc_sanitize_login_redirect_option'] // Sanitize the input value as login redirect
            ]
        );
    }

    // Sanitize the display name option
    public function scouting_oidc_sanitize_display_name_option($input) {
        // Define allowed options
        $valid = ['fullname', 'firstname', 'lastname', 'username'];
        
        // Return the input if it’s a valid option; otherwise, default to 'fullname'
        return in_array($input, $valid, true) ? $input : 'fullname';
    }

    // Sanitize the input value as boolean
    public function scouting_oidc_sanitize_boolean_option($input) {
        return $input ? 1 : 0;
    }

    // Sanitize the login redirect option
    public function scouting_oidc_sanitize_login_redirect_option($input) {
        // Define allowed options
        $valid = ['default', 'frontpage', 'dashboard'];
        
        // Return the input if it’s a valid option; otherwise, default to 'default'
        return in_array($input, $valid, true) ? $input : 'default';
    }

    // Callback to render section content
    public function scouting_oidc_settings_general_callback() {}

    // Callback to render selectbox field
    public function scouting_oidc_settings_general_display_name_callback() {
        $possible_values = array(
            'fullname' => __('Full name', 'scouting-openid-connect'),
            'firstname' => __('First name', 'scouting-openid-connect'),
            'lastname' => __('Last name', 'scouting-openid-connect'),
            'username' => __('Username', 'scouting-openid-connect'),
        );
        $value = get_option('scouting_oidc_user_display_name');
        
        echo '<select id="scouting_oidc_user_display_name" name="scouting_oidc_user_display_name" style="width: 177px;">';
        foreach ($possible_values as $key => $name) {
            if ($value == $key)
                echo '<option value="' . esc_attr($key) . '" selected>' . esc_html($name) . '</option>';
            else
                echo '<option value="' . esc_attr($key) . '">' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }

    // Callback to render checkbox field
    public function scouting_oidc_settings_general_birthdate_callback() {
        if (get_option('scouting_oidc_user_birthdate'))
            echo '<input type="checkbox" id="scouting_oidc_user_birthdate" name="scouting_oidc_user_birthdate" checked/>';
        else
            echo '<input type="checkbox" id="scouting_oidc_user_birthdate" name="scouting_oidc_user_birthdate"/>';
    }

    // Callback to render checkbox field
    public function scouting_oidc_settings_general_gender_callback() {
        if (get_option('scouting_oidc_user_gender'))
            echo '<input type="checkbox" id="scouting_oidc_user_gender" name="scouting_oidc_user_gender" checked/>';
        else 
            echo '<input type="checkbox" id="scouting_oidc_user_gender" name="scouting_oidc_user_gender"/>';
    }

    // Callback to render checkbox field
    public function scouting_oidc_settings_general_scouting_id_callback() {
        if (get_option('scouting_oidc_user_scouting_id'))
            echo '<input type="checkbox" id="scouting_oidc_user_scouting_id" name="scouting_oidc_user_scouting_id" checked/>';
        else
            echo '<input type="checkbox" id="scouting_oidc_user_scouting_id" name="scouting_oidc_user_scouting_id"/>';
    }

    // Callback to render checkbox field
    public function scouting_oidc_settings_general_user_auto_create_callback() {
        if (get_option('scouting_oidc_user_auto_create'))
            echo '<input type="checkbox" id="scouting_oidc_user_auto_create" name="scouting_oidc_user_auto_create" checked/>';
        else
            echo '<input type="checkbox" id="scouting_oidc_user_auto_create" name="scouting_oidc_user_auto_create"/>';
    }

    // Callback to render text field
    public function scouting_oidc_settings_general_user_name_prefix_callback() {
        $value = get_option('scouting_oidc_user_name_prefix');
        echo '<input type="text" id="scouting_oidc_user_name_prefix" name="scouting_oidc_user_name_prefix" placeholder="Prefix Username" value="' . esc_attr($value) . '" size="20" />';
        echo '<p class="description">' . esc_html__("This prefix will be added to the username of all Scouting Nederland users", "scouting-openid-connect") . '</p>';
    }

    // Callback to render checkbox field
    public function scouting_oidc_settings_general_user_redirect_callback() {
        if (get_option('scouting_oidc_user_redirect'))
            echo '<input type="checkbox" id="scouting_oidc_user_redirect" name="scouting_oidc_user_redirect" checked/>';
        else
            echo '<input type="checkbox" id="scouting_oidc_user_redirect" name="scouting_oidc_user_redirect"/>';
    }

    // Callback to render selectbox field
    public function scouting_oidc_settings_general_login_redirect_callback() {
        $possible_values = array(
            'default' => __('Default (no action)', 'scouting-openid-connect'),
            'frontpage' => __('Frontpage', 'scouting-openid-connect'),
            'dashboard' => __('Dashboard', 'scouting-openid-connect'),
        );
        $value = get_option('scouting_oidc_login_redirect');
        
        echo '<select id="scouting_oidc_login_redirect" name="scouting_oidc_login_redirect" style="width: 177px;">';
        foreach ($possible_values as $key => $name) {
            if ($value == $key)
                echo '<option value="' . esc_attr($key) . '" selected>' . esc_html($name) . '</option>';
            else
                echo '<option value="' . esc_attr($key) . '">' . esc_html($name) . '</option>';
        }
        echo '</select>';
    }
}
?>