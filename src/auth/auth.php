<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) .'OpenIDConnectClient.php';
require_once plugin_dir_path(__FILE__) . '../../src/user/user.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

use ScoutingOIDC\User;

class Auth {
    /**
     * @var OpenIDConnectClient OpenID Connect client
     */
    private $oidc_client;

    public function __construct() {
        $this->oidc_client = new OpenIDConnectClient(
            sanitize_text_field(get_option('scouting_oidc_client_id')),
            sanitize_text_field(get_option('scouting_oidc_client_secret')),
            get_site_url(),
            'https://login.scouting.nl'  // Trusted external URL
       );
    }

    // Add the OpenID Connect button to the login form
    public function scouting_oidc_auth_login_form() {
        // Check if the client ID and client secret are empty 
        if (empty(get_option('scouting_oidc_client_id')) || empty(get_option('scouting_oidc_client_secret'))) {
            return;
        }

        $login_url = $this->scouting_oidc_auth_login_url();

        // Check if the login URL starts with 'init_error'
        if (substr($login_url, 0, 10) == 'init_error') {
            return;
        }

        // Add divider to the login form to separate the default login form from the OpenID Connect button
        echo '<hr id="scouding-oidc-divider" style="border-top: 2px solid #8c8f94; border-radius: 4px;"/>';

        // Button style
        $button_style = "display: -webkit-box; display: -ms-flexbox; display: -webkit-flex; display: flex; justify-content: center; align-items: center; background-color: #4CAF50; color: #ffffff; border: none; border-radius: 4px; text-decoration: none; font-weight: bold; width: 100%; height: 100%; text-align: center;";

        // Add the OpenID Connect button to the login form
        echo '<div id="scouting-oidc-login-div" style="margin: 16px 0px; width: 100%; height: 40px;">';
        echo '<a id="scouting-oidc-login-link" href="' . esc_url($login_url) . '" style="' . esc_attr($button_style) . '">';
        echo wp_kses($this->scouting_oidc_auth_icon(), $this->scouting_oidc_auth_icon_wp_kses_allowed_svg());
        echo '<span id="scouting-oidc-login-text">' . esc_html__('Login with Scouts Online', 'scouting-openid-connect') . '</span>';
        echo '</a></div>';
    }

    // Create shortcode with a login button
    public function scouting_oidc_auth_login_button_shortcode($atts) {
        // Check if the client ID and client secret are empty 
        if (empty(get_option('scouting_oidc_client_id')) || empty(get_option('scouting_oidc_client_secret'))) {
            return;
        }

        $login_url = $this->scouting_oidc_auth_login_url();

        // Check if the login URL starts with 'init_error'
        if (substr($login_url, 0, 10) == 'init_error') {
            return;
        }

        // Extract shortcode attributes (if any)
        $atts = shortcode_atts(
            array(
                'width' => '250',                // Default width in pixels
                'height' => '40',                // Default height in pixels
                'background_color' => '#4CAF50', // Default background color
                'text_color' => '#ffffff',       // Default text color
            ),
            $atts,
            'scouting_oidc_button' // Name of your shortcode
        );

        // Ensure minimal button dimensions and sanitize
        $atts['width'] = max(120, intval($atts['width']));
        $atts['height'] = max(40, intval($atts['height']));
        $atts['background_color'] = sanitize_hex_color($atts['background_color']);
        $atts['text_color'] = sanitize_hex_color($atts['text_color']);

        // Button style
        $button_style = "display: flex; justify-content: center; align-items: center; background-color: " . esc_attr($atts['background_color']) . "; color: " . esc_attr($atts['text_color']) . "; border: none; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; width: 100%; height: 100%; text-align: center;";
        
        $button_html = '<div id="scouting-oidc-login-div" style="min-width: 120px; width: ' . esc_attr($atts['width']) . 'px; min-height: 40px; height: ' . esc_attr($atts['height']) . 'px;">';
        $button_html .= '<a id="scouting-oidc-login-link" href="' . esc_url($login_url) . '" style="' . esc_attr($button_style) . '">';
        // If width is smaller than 225px, the image will not be displayed
        if (intval($atts['width']) >= 225) {
            $button_html .= wp_kses($this->scouting_oidc_auth_icon(), $this->scouting_oidc_auth_icon_wp_kses_allowed_svg());
        }
        $button_html .= '<span id="scouting-oidc-login-text">' . esc_html__('Login with Scouts Online', 'scouting-openid-connect') . '</span>';
        $button_html .= '</a></div>';

        return $button_html;
    }

    // Create shortcode with the OpenID Authentication URL
    public function scouting_oidc_auth_login_url_shortcode() {
        // Check if the client ID and client secret are empty 
        if (empty(get_option('scouting_oidc_client_id')) || empty(get_option('scouting_oidc_client_secret'))) {
            $hint = rawurlencode(__('Client ID or Client Secret are missing in the configuration', 'scouting-openid-connect'));
            return esc_url_raw(wp_login_url() . '?login=failed&error_description=init&hint=' . $hint . '&message=init_error');
        }

        $login_url = $this->scouting_oidc_auth_login_url();

        // Check if the login URL starts with 'init_error'
        if (substr($login_url, 0, 10) == 'init_error') {
            // Get hint from the URL
            $hint = substr($login_url, 12);

            // Return login URL with hint
            return esc_url_raw(wp_login_url() . '?login=failed&error_description=init&hint=' . $hint . '&message=init_error');
        }
        return esc_url($this->scouting_oidc_auth_login_url());
    }

    // Callback to login with OpenID Connect
    public function scouting_oidc_auth_callback() {
        // Check if we're on the front page
        if (!is_front_page()) {
            return;
        }

        // Check if user is logged in
        if (is_user_logged_in()) {
            return;
        }

        // Check if nonce is valid with wp_verify_nonce
        if (wp_verify_nonce($this->oidc_client->getNonce())) {
            $this->oidc_client->unsetStatesAndNonce();

            $hint = rawurlencode(__('Nonce is invalid', 'scouting-openid-connect'));

            $redirect_url = esc_url_raw(wp_login_url() . '?login=failed&error_description=error&hint=' . $hint . '&message=nonce_invalid');
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Check if eror_description, hint, and message are set in the URL and sanitize them before redirecting
        if (isset($_GET['error_description'], $_GET['hint'], $_GET['message'])) {
            $this->oidc_client->unsetStatesAndNonce();

            $error_description = rawurlencode(sanitize_text_field(wp_unslash($_GET['error_description'])));
            $hint = rawurlencode(sanitize_text_field(wp_unslash($_GET['hint'])));
            $message = rawurlencode(sanitize_text_field(wp_unslash($_GET['message'])));

            $redirect_url = esc_url_raw(wp_login_url() . '?login=failed&error_description=' . $error_description . '&hint=' . $hint . '&message=' . $message);
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Check if 'state' parameter is set in the URL
        if (!isset($_GET['state'])) {
            return;
        }

        // Verify state parameter for security
        if (!$this->oidc_client->hasstate(sanitize_text_field(wp_unslash($_GET['state'])))) {
            $this->oidc_client->unsetStatesAndNonce();

            $hint = rawurlencode(__('State is invalid', 'scouting-openid-connect'));

            $redirect_url = esc_url_raw(wp_login_url() . '?login=failed&error_description=error&hint=' . $hint . '&message=state_invalid');
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Check if 'code' parameter is set in the URL
        if (!isset($_GET['code'])) {
            $this->oidc_client->unsetStatesAndNonce();

            $hint = rawurlencode(__('Code is missing', 'scouting-openid-connect'));

            $redirect_url = esc_url_raw(wp_login_url() . '?login=failed&error_description=error&hint=' . $hint . '&message=code_missing');
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Retrieve tokens from the OpenID Connect server and sanitize the 'code' parameter
        $this->oidc_client->retrieveTokens(sanitize_text_field(wp_unslash($_GET['code'])));

        // Validate the ID token
        $user_json_encoded = $this->oidc_client->validateTokens();

        // Create a new User object
        $user = new User($user_json_encoded);
        
        // Check if user is already created
        if ($user->scouting_oidc_user_check_if_exist()) {
            $user->scouting_oidc_user_update();
            $user->scouting_oidc_user_login();
        } else {
            if (get_option('scouting_oidc_user_auto_create')) {
                $user->scouting_oidc_user_create();
                $user->scouting_oidc_user_login();
            } else {
                $hint = rawurlencode(__('Webmaster disabled creation of new accounts', 'scouting-openid-connect'));
                $redirect_url = esc_url_raw(wp_login_url() . "?login=failed&error_description=error&hint={$hint}&message=disabled_auto_create");
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
    }

    // Callback after failed login
    public function scouting_oidc_auth_login_failed($message) {
        // Check if user is logged in
        if (!is_login()) {
            return;
        }

        // Check if nonce is valid
        if (wp_verify_nonce($this->oidc_client->getNonce())) {
            $this->oidc_client->unsetStatesAndNonce();
        }

        // Check if error_description, hint, and message are set in the URL
        if (!isset($_GET['error_description'], $_GET['hint'], $_GET['message'])) {
            return;
        }

        $error_description = sanitize_text_field(wp_unslash($_GET['error_description']));
        $error_message = sanitize_text_field(wp_unslash($_GET['message']));
        $hint = sanitize_text_field(wp_unslash($_GET['hint']));

        // If the error equals `The user denied the request`, show a translated message
        if ($hint == 'The user denied the request') {
            $hint = __("The user denied the request", "scouting-openid-connect");
        }

        // If $hint contains Details: then put it on a new line and make it bold
        $details = __('Details:', 'scouting-openid-connect');
        if (strpos($hint, $details) !== false) {
            $details = explode($details, $hint);
            $error = '<div id="login_error" class="notice notice-error"><p><strong>Error: </strong>';
            $error .= esc_html($details[0]);
            $error .= '<br><strong>Details:</strong>';
            $error .= esc_html($details[1]);
            $error .= '</p></div>';
            return $error;
        }

        // Display the error message
        return '<div id="login_error" class="notice notice-error"><p><strong>Error: </strong>' . esc_html($hint) . '</p></div>';
    }

    // Redirect after login based on settings
    public function scouting_oidc_auth_login_redirect() {
        if (get_option('scouting_oidc_login_redirect') == "dashboard") {
            wp_safe_redirect(admin_url());
            exit;
        } else if (get_option('scouting_oidc_login_redirect') == "frontpage") {
            wp_safe_redirect(home_url());
            exit;
        }
    }

    // Redirect after logout based on settings
    public function scouting_oidc_auth_logout_redirect() {
        $logout_url = esc_url_raw($this->oidc_client->getLogoutUrl());
        wp_redirect($logout_url);
        exit;
    }

    // Helper function to get the icon URL
    private function scouting_oidc_auth_icon() {
        // Define the path to the SVG file
        $svg_file_path = SCOUTING_OIDC_PATH . 'assets/icon.svg';

        // Check if the file exists
        if (file_exists($svg_file_path)) {
            // Get the contents of the SVG file
            $wp_filesystem = new \WP_Filesystem_Direct(null);
            $svg_content = $wp_filesystem->get_contents($svg_file_path);

            // Modify the SVG tag to include additional attributes
            $svg_content = preg_replace(
                '/<svg([^>]+)>/',
                '<svg\1 id="scouting-oidc-login-img" style="width: 2.5rem; height: 2.5rem; margin-right: 10px;" role="img" aria-label="Scouting NL Logo">',
                $svg_content
            );

            // Return the SVG content
            return $svg_content;
        }
    }

    // Helper function to get the allowed SVG tags
    private function scouting_oidc_auth_icon_wp_kses_allowed_svg () {
        return array(
            'svg' => array(
                'version' => true,
                'xmlns' => true,
                'viewbox' => true,
                'id' => true,
                'style' => true,
                'role' => true,
                'aria-label' => true,
            ),
            'title' => array(),
            'style' => array(),
            'g' => array(
                'id' => true,
            ),
            'path' => array(
                'id' => true,
                'class' => true,
                'd' => true,
            ),
        );
    }

    // Helper function to get the login URL
    private function scouting_oidc_auth_login_url() {
        $response_type = 'code';
        $scopes = array_map('sanitize_text_field', explode(" ", get_option('scouting_oidc_scopes')));

        // Check if nonce is valid
        if (wp_verify_nonce($this->oidc_client->getNonce())) {
            $this->oidc_client->unsetStatesAndNonce();
        }
        
        // Check if error_description, hint, and message are set in the URL
        if (isset($_GET['error_description'], $_GET['hint'])) {
            $error_description = sanitize_text_field(wp_unslash($_GET['error_description']));
            $hint = sanitize_text_field(wp_unslash($_GET['hint']));
            if ($error_description == 'init')
                return "init_error:" . $hint;
        }

        return $this->oidc_client->getAuthenticationURL($response_type, $scopes);
    }
}
?>