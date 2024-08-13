<?php

require_once 'OpenIDConnectClient.php';
require_once __DIR__.'/../../src/user/user.php';

class Auth {

    /**
     * @var OpenIDConnectClient OpenID Connect client
     */
    private $oidc_client;

    public function __construct() {
        $this->oidc_client = new OpenIDConnectClient(
            get_option('scouting_oidc_client_id'),
            get_option('scouting_oidc_client_secret'),
            get_site_url(),
            'https://login.scouting.nl',
       );
    }

    // Add the OpenID Connect button to the login form
    public function scouting_oidc_login_form() {
        // Add divider to the login form to separate the default login form from the OpenID Connect button
        echo '<hr id="scouding-oidc-divider" style="border-top: 2px solid #8c8f94; border-radius: 4px;"/>';

        // Button style
        $button_style = "display: -webkit-box; display: -ms-flexbox; display: -webkit-flex; display: flex; justify-content: center; align-items: center; background-color: #4CAF50; color: #ffffff; border: none; border-radius: 4px; text-decoration: none; font-weight: bold; width: 100%; height: 100%; text-align: center;";

        // Add the OpenID Connect button to the login form
        echo '<div id="scouting-oidc-login-div" style="margin: 16px 0px; width: 100%; height: 40px;">';
        echo '<a id="scouting-oidc-login-link" href="' . esc_url($this->get_login_url()) . '" style="' . esc_attr($button_style) . '">';
        echo '<img id="scouting-oidc-login-img" src="' . esc_url($this->get_icon_url()) . '" alt="Scouting NL Logo" style="width: 40px; height: 40px; margin-right: 10px;">';
        echo '<span id="scouting-oidc-login-text">' . esc_html__('Login with Scouts Online', 'scouting-openid-connect') . '</span>';
        echo '</a></div>';
    }

    // Create shortcode with a login button
    public function scouting_oidc_login_button_shortcode($atts) {
        // Extract shortcode attributes (if any)
        $atts = shortcode_atts(
            array(
                'width' => '250', // Default width in pixels
                'height' => '40', // Default height in pixels
                'background_color' => '#4CAF50', // Default background color
                'text_color' => '#ffffff', // Default text color
           ),
            $atts,
            'scouting_oidc_button' // Name of your shortcode
       );

        // Ensure minimal button dimensions
        $atts['width'] = max(120, intval($atts['width']));
        $atts['height'] = max(40, intval($atts['height']));

        // Button style
        $button_style = "display: flex; justify-content: center; align-items: center; background-color: " . esc_attr($atts['background_color']) . "; color: " . esc_attr($atts['text_color']) . "; border: none; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; width: 100%; height: 100%; text-align: center;";
        
        $button_html = '<div id="scouting-oidc-login-div" style="min-width: 120px; width: ' . esc_attr($atts['width']) . 'px; min-height: 40px; height: ' . esc_attr($atts['height']) . 'px;">';
        $button_html .= '<a id="scouting-oidc-login-link" href="' . esc_url($this->get_login_url()) . '" style="' . esc_attr($button_style) . '">';
        // If width is smaller than 225px, the image will not be displayed
        if (intval($atts['width']) >= 225) {
            $button_html .= '<img id="scouting-oidc-login-img" src="' . esc_url($this->get_icon_url()) . '" alt="Scouting NL Logo" style="width: 40px; height: 40px; margin-right: 10px;">';
        }
        $button_html .= '<span id="scouting-oidc-login-text">' . esc_html__('Login with Scouts Online', 'scouting-openid-connect') . '</span>';
        $button_html .= '</a></div>';

        return $button_html;
    }

    // Create shortcode with the OpenID Authentication URL
    public function scouting_oidc_login_url_shortcode() {
        return $this->get_login_url();
    }

    // Callback to login with OpenID Connect
    public function scouting_oidc_callback() {
        // Check if we're on the front page
        if (!is_front_page() || !is_home()) {
            return;
        }

        if (is_user_logged_in()) {
            return;
        }

        // Check if 'error' and 'error_description' parameter is set in the URL
        if (isset($_GET['error_description']) && isset($_GET['hint']) && isset($_GET['message'])) {
            $this->oidc_client->unsetStateAndNonce();
            wp_safe_redirect(wp_login_url() . '?error_description=' . $_GET['error_description'] . '&hint=' . $_GET['hint'] . '&message=' . $_GET['message']);
            exit;
        }

        // Check if 'state' parameter is set in the URL
        if (!isset($_GET['state'])) {
            return;
        }

        // Verify state parameter for security
        $state = $this->oidc_client->getState();
        if ($state === null || $_GET['state'] !== $state) {
            return;
        }

        // Check if 'code' parameter is set in the URL
        if (!isset($_GET['code'])) {
            return;
        }

        // Retrieve tokens from the OpenID Connect server
        $this->oidc_client->retrieveTokens($_GET['code']);

        // Validate the ID token
        $user_json_encoded = $this->oidc_client->validateTokens();

        // Create a new User object
        $user = new User($user_json_encoded);
        
        // Check if user is already created
        if ($user->checkIfUserExist()) {
            $user->updateUser();
            $user->loginUser();
        } else {
            if (get_option('scouting_oidc_user_auto_create')) {
                $user->createUser();
                $user->loginUser();
            } else {
                wp_safe_redirect(wp_login_url() . '?error_description=error&hint=' . __("Webmaster disabled creation of new accounts", "scouting-openid-connect") . '&message=disabled_auto_create');
                exit;
            }
        }
    }

    // Callback after failed login
    public function scouting_oidc_login_failed($message) {
        if (!is_login()) {
            return;
        }

        if (!isset($_GET['error_description']) && !isset($_GET['hint']) && !isset($_GET['message'])) {
            return;
        }

        $error_description = $_GET['error_description'];
        $hint = $_GET['hint'];
        $message = $_GET['message'];

        // If the error equals `The user denied the request`, show a translated message
        if ($hint == 'The user denied the request') {
            $hint = __("The user denied the request", "scouting-openid-connect");
        }

        return '<div id="login_error" class="notice notice-error"><p><strong>Error: </strong>' . esc_html($hint) . '</p></div>';
    }

    // Redirect after login based on settings
    public function scouting_oidc_login_redirect() {
        if (get_option('scouting_oidc_login_redirect') == "dashboard") {
            wp_safe_redirect(admin_url());
            exit;
        } else if (get_option('scouting_oidc_login_redirect') == "frontpage") {
            wp_safe_redirect(home_url());
            exit;
        }
    }

    // Redirect after logout based on settings
    public function scouting_oidc_logout_redirect() {
        $logout_url = $this->oidc_client->getLogoutUrl();
        wp_redirect($logout_url);
        exit;
    }

    // Helper function to get the icon URL
    private function get_icon_url() {
        return plugins_url('../../assets/icon.svg', __FILE__);
    }

    // Helper function to get the login URL
    private function get_login_url() {
        $response_type = 'code';
        $scopes = explode(" ", get_option('scouting_oidc_scopes'));
        return $this->oidc_client->getAuthenticationURL($response_type, $scopes);
    }
}
?>
