<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 1.0.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the class-openidconnectclient.php implementation.
 */
require_once plugin_dir_path( __FILE__ ) . 'class-openidconnectclient.php';
/**
 * Loads the class-user.php implementation.
 */
require_once plugin_dir_path( __FILE__ ) . '../../src/user/class-user.php';
/**
 * Loads the class-errorhandler.php implementation.
 */
require_once plugin_dir_path( __FILE__ ) . '../../src/utilities/class-errorhandler.php';
/**
 * Loads the class-wp-filesystem-base.php implementation.
 */
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
/**
 * Loads the class-wp-filesystem-direct.php implementation.
 */
require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

use ScoutingOIDC\User;
use ScoutingOIDC\ErrorHandler;

/**
 * Handles Scouting OpenID Connect authentication.
 *
 * @since 1.0.0
 */
class Auth {
	/**
	 * The OIDC client.
	 *
	 * @since 1.0.0
	 * @var OpenIDConnectClient OpenID Connect client.
	 */
	private $oidc_client;

	/**
	 * Initializes the OIDC authentication client.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->oidc_client = new OpenIDConnectClient(
			sanitize_text_field( get_option( 'scouting_oidc_client_id' ) ),
			sanitize_text_field( get_option( 'scouting_oidc_client_secret' ) ),
			home_url(),
			'https://login.scouting.nl'
		);
	}

	/**
	 * Adds the OpenID Connect button to the login form.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_auth_login_form(): void {
		// Check if the client ID and client secret are empty.
		if ( empty( get_option( 'scouting_oidc_client_id' ) ) || empty( get_option( 'scouting_oidc_client_secret' ) ) ) {
			Logger::warning( LogComponent::AUTH, 'Client ID or Client Secret are missing in the configuration, login button will not be rendered on the login form' );
			return;
		}

		$login_url = $this->scouting_oidc_auth_login_url();

		// Check if the login URL starts with 'init_error'.
		if ( substr( $login_url, 0, 10 ) === 'init_error' ) {
			Logger::warning( LogComponent::AUTH, 'Failed to generate OIDC login URL, login button will not be rendered on the login form' );
			return;
		}

		// Add divider to the login form to separate the default login form from the OpenID Connect button.
		echo '<hr id="scouting-oidc-divider" style="border-top: 2px solid #8c8f94; border-radius: 4px;"/>';

		// Button style.
		$button_style = 'display: -webkit-box; display: -ms-flexbox; display: -webkit-flex; display: flex; justify-content: center; align-items: center; background-color: #4CAF50; color: #ffffff; border: none; border-radius: 4px; text-decoration: none; font-weight: bold; width: 100%; height: 100%; text-align: center;';

		// Add the OpenID Connect button to the login form.
		echo '<div id="scouting-oidc-login-div" style="margin: 16px 0px; width: 100%; height: 40px;">';
		echo '<a id="scouting-oidc-login-link" href="' . esc_url( $login_url ) . '" style="' . esc_attr( $button_style ) . '">';
		echo wp_kses( $this->scouting_oidc_auth_icon(), $this->scouting_oidc_auth_icon_wp_kses_allowed_svg() );
		echo '<span id="scouting-oidc-login-text">' . esc_html__( 'Login with Scouts Online', 'scouting-openid-connect' ) . '</span>';
		echo '</a></div>';
	}

	/**
	 * Creates shortcode with a login button.
	 *
	 * @since 1.0.0
	 * @since 2.3.0 Made the `$atts` parameter optional. Updated the method signature.
	 * @since 2.4.0 Added redirect_back support for the login button shortcode.
	 *
	 * @param array $atts Optional. The shortcode attributes for customizing the button (width, height, background_color,
	 *                       text_color, hide_logo). Default empty array.
	 * @return string The HTML for the login/logout button, or an empty string if the button cannot be
	 *               rendered due to missing configuration or errors.
	 */
	public function scouting_oidc_auth_login_button_shortcode( array $atts = array() ): string {
		$default_background_color = '#4CAF50';
		$default_text_color       = '#ffffff';

		// Extract shortcode attributes (if any).
		$atts = shortcode_atts(
			array(
				'width'            => '250',
				'height'           => '40',
				'background_color' => $default_background_color,
				'text_color'       => $default_text_color,
				'hide_logo'        => 'false',
				'redirect_back'    => 'false',
			),
			$atts,
			'scouting_oidc_button'
		);

		// Ensure minimal button dimensions and sanitize.
		$atts['width']            = max( 120, intval( $atts['width'] ) );
		$atts['height']           = max( 40, intval( $atts['height'] ) );
		$background_color         = sanitize_hex_color( $atts['background_color'] );
		$text_color               = sanitize_hex_color( $atts['text_color'] );
		$atts['background_color'] = $background_color ? $background_color : $default_background_color;
		$atts['text_color']       = $text_color ? $text_color : $default_text_color;

		// Parse boolean-like shortcode attributes (true/false, 1/0, yes/no, on/off).
		$hide_logo     = filter_var( (string) $atts['hide_logo'], FILTER_VALIDATE_BOOLEAN );
		$redirect_back = filter_var( (string) ( $atts['redirect_back'] ?? 'false' ), FILTER_VALIDATE_BOOLEAN );

		$button_container_id = wp_unique_id( 'scouting-oidc-login-div-' );
		$button_link_id      = wp_unique_id( 'scouting-oidc-login-link-' );
		$button_text_id      = wp_unique_id( 'scouting-oidc-login-text-' );
		$button_icon_id      = wp_unique_id( 'scouting-oidc-login-img-' );

		if ( is_user_logged_in() ) {
			// Use the standard WP logout URL, plugin logout hook will forward to OIDC provider logout endpoint.
			$button_url  = wp_logout_url( home_url() );
			$button_text = esc_html__( 'Logout', 'scouting-openid-connect' );
		} else {
			// Check if the client ID and client secret are empty.
			if ( empty( get_option( 'scouting_oidc_client_id' ) ) || empty( get_option( 'scouting_oidc_client_secret' ) ) ) {
				Logger::critical( LogComponent::AUTH, 'Client ID or Client Secret are missing in the configuration, shortcode button will not be rendered' );
				return '';
			}

			// If redirect_back is requested, build a return URL to the current page and pass it to the auth URL builder.
			if ( $redirect_back ) {
				$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
				$current_url = home_url( $request_uri );
				$login_url   = $this->scouting_oidc_auth_login_url( $current_url );
			} else {
				$login_url = $this->scouting_oidc_auth_login_url();
			}

			// Check if the login URL starts with 'init_error'.
			if ( substr( $login_url, 0, 10 ) === 'init_error' ) {
				Logger::error( LogComponent::AUTH, 'Failed to generate OIDC login URL, shortcode button will not be rendered' );
				return '';
			}

			$button_url  = $login_url;
			$button_text = esc_html__( 'Login with Scouts Online', 'scouting-openid-connect' );
		}

		// Button style.
		$button_style = 'display: flex; justify-content: center; align-items: center; background-color: ' . esc_attr( $atts['background_color'] ) . '; color: ' . esc_attr( $atts['text_color'] ) . '; border: none; border-radius: 4px; text-decoration: none; font-size: 13px; font-weight: bold; width: 100%; height: 100%; text-align: center;';

		$button_html  = '<div id="' . esc_attr( $button_container_id ) . '" class="scouting-oidc-login-div" style="min-width: 120px; width: ' . esc_attr( $atts['width'] ) . 'px; min-height: 40px; height: ' . esc_attr( $atts['height'] ) . 'px;">';
		$button_html .= '<a id="' . esc_attr( $button_link_id ) . '" class="scouting-oidc-login-link" href="' . esc_url( $button_url ) . '" style="' . esc_attr( $button_style ) . '">';
		// Show logo only when explicitly allowed and there is enough width.
		if ( ! $hide_logo && intval( $atts['width'] ) >= 225 ) {
			$button_html .= wp_kses( $this->scouting_oidc_auth_icon( $button_icon_id ), $this->scouting_oidc_auth_icon_wp_kses_allowed_svg() );
		}
		$button_html .= '<span id="' . esc_attr( $button_text_id ) . '" class="scouting-oidc-login-text">' . $button_text . '</span>';
		$button_html .= '</a></div>';

		return $button_html;
	}

	/**
	 * Creates shortcode with the OpenID Authentication URL.
	 *
	 * @since 1.0.0
	 * @since 2.4.0 Added the `$atts` parameter.
	 *
	 * @param array $atts Optional. Shortcode attributes. Default empty array.
	 * @return string the HTML for the login URL or an error URL if the login URL cannot be generated.
	 */
	public function scouting_oidc_auth_login_url_shortcode( array $atts = array() ): string {
		// Allow a `redirect_back` attribute for the link shortcode as well.
		$atts = shortcode_atts(
			array(
				'redirect_back' => 'false',
			),
			$atts,
			'scouting_oidc_link'
		);

		$redirect_back = filter_var( (string) ( $atts['redirect_back'] ?? 'false' ), FILTER_VALIDATE_BOOLEAN );

		// If the user is already logged in, return the logout URL instead of the login URL.
		if ( is_user_logged_in() ) {
			return esc_url( wp_logout_url( home_url() ) );
		}

		// Check if the client ID and client secret are empty.
		if ( empty( get_option( 'scouting_oidc_client_id' ) ) || empty( get_option( 'scouting_oidc_client_secret' ) ) ) {
			Logger::critical( LogComponent::AUTH, 'Client ID or Client Secret are missing in the configuration, shortcode login URL will be rendered as an login error URL' );
			return ErrorHandler::login_error_url( 'init', __( 'Client ID or Client Secret are missing in the configuration', 'scouting-openid-connect' ), 'init_error' );
		}

		if ( $redirect_back ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			$current_url = home_url( $request_uri );
			$login_url   = $this->scouting_oidc_auth_login_url( $current_url );
		} else {
			$login_url = $this->scouting_oidc_auth_login_url();
		}

		// Check if the login URL starts with 'init_error'.
		if ( substr( $login_url, 0, 10 ) === 'init_error' ) {
			// Get hint from the URL.
			$hint = substr( $login_url, 12 );

			Logger::critical( LogComponent::AUTH, 'Failed to generate OIDC login URL, shortcode login URL will be rendered as an login error URL' );

			// Return login URL with hint.
			return ErrorHandler::login_error_url( 'init', $hint, 'init_error' );
		}
		return esc_url( $login_url );
	}

	/**
	 * Handles login with OpenID Connect.
	 *
	 * @since 1.0.0
	 * @since 2.5.0 Retrieved profile claims from the UserInfo endpoint after subject binding.
	 */
	public function scouting_oidc_auth_callback(): void {
		// Check if we're on the front page.
		if ( ! is_front_page() ) {
			return;
		}

		// Check if user is logged in.
		if ( is_user_logged_in() ) {
			return;
		}

		// All raw $_GET reads collected here.
		$param_error_description_raw = filter_input( INPUT_GET, 'error_description', FILTER_UNSAFE_RAW );
		$param_hint_raw              = filter_input( INPUT_GET, 'hint', FILTER_UNSAFE_RAW );
		$param_error_raw             = filter_input( INPUT_GET, 'error', FILTER_UNSAFE_RAW );
		$param_message_raw           = filter_input( INPUT_GET, 'message', FILTER_UNSAFE_RAW );
		$param_state_raw             = filter_input( INPUT_GET, 'state', FILTER_UNSAFE_RAW );
		$param_code_raw              = filter_input( INPUT_GET, 'code', FILTER_UNSAFE_RAW );

		// All parameters are sanitized as they may contain untrusted data.
		$param_error_description = is_string( $param_error_description_raw ) ? sanitize_text_field( $param_error_description_raw ) : null;
		$param_hint              = is_string( $param_hint_raw ) ? sanitize_text_field( $param_hint_raw ) : null;
		$param_error             = is_string( $param_error_raw ) ? sanitize_text_field( $param_error_raw ) : null;
		$param_message           = is_string( $param_message_raw ) ? sanitize_text_field( $param_message_raw ) : null;
		$param_state             = is_string( $param_state_raw ) ? sanitize_text_field( $param_state_raw ) : null;
		$param_code              = is_string( $param_code_raw ) ? sanitize_text_field( $param_code_raw ) : null;

		// Handle error callback parameters and forward them to wp-login.
		if ( filter_has_var( INPUT_GET, 'error_description' ) && filter_has_var( INPUT_GET, 'hint' ) ) {
			$this->oidc_client->unset_states_and_nonce();

			$message = $param_message ?? ( $param_error ?? 'error' );

			ErrorHandler::redirect_to_login_error( $param_error_description ?? '', $param_hint ?? '', $message, $param_error );
		}

		// Check if 'state' parameter is set in the URL.
		if ( ! filter_has_var( INPUT_GET, 'state' ) ) {
			return;
		}

		// Verify state parameter for security.
		$state = $param_state ?? '';

		// If the state is invalid, unset states and nonce, then redirect to login page with an error message.
		if ( ! $this->oidc_client->has_state( $state ) ) {
			$this->oidc_client->unset_states_and_nonce();
			ErrorHandler::redirect_to_login_error( 'error', __( 'State is invalid', 'scouting-openid-connect' ), 'state_invalid' );
		}

		// Check if 'code' parameter is set in the URL.
		if ( ! filter_has_var( INPUT_GET, 'code' ) ) {
			$this->oidc_client->unset_states_and_nonce();
			ErrorHandler::redirect_to_login_error( 'error', __( 'Code is missing', 'scouting-openid-connect' ), 'code_missing' );
		}

		// Validate that the 'code' parameter is a non-empty string.
		$param_code = is_string( $param_code ?? null ) ? trim( $param_code ) : '';
		if ( '' === $param_code ) {
			$this->oidc_client->unset_states_and_nonce();
			ErrorHandler::redirect_to_login_error( 'error', __( 'Code is missing', 'scouting-openid-connect' ), 'code_missing' );
		}

		// Fetch nonce bound to this specific state before retrieve_tokens clears session data.
		$stored_nonce = $this->oidc_client->get_nonce_for_state( $state );

		// Retrieve tokens from the OpenID Connect server using the validated 'code' parameter.
		$this->oidc_client->retrieve_tokens( $param_code, $state );

		// Validate the ID token, passing the stored nonce for claim verification.
		$id_token_claims = $this->oidc_client->validate_tokens( $stored_nonce );

		// Retrieve current user claims from the discovered UserInfo endpoint.
		$user_info = $this->oidc_client->retrieve_user_info( $id_token_claims['sub'] );

		// Create a new User object.
		$user = new User( $user_info );

		Logger::info( LogComponent::AUTH, "User '{$user->get_display_name()}' is being checked for login or account creation", null, $user->get_username() );

		// Check if user is already created.
		if ( $user->scouting_oidc_user_check_if_exist() ) {
			Logger::info( LogComponent::AUTH, "User '{$user->get_display_name()}' has an existing account, updating user information and logging in", null, $user->get_username() );
			$user->scouting_oidc_user_update();
			// Promote per-state redirect only after successful callback validation and right before login.
			$this->oidc_client->apply_redirect_for_state_to_session( $state );
			$user->scouting_oidc_user_login();
		} elseif ( get_option( 'scouting_oidc_user_auto_create' ) ) {
				Logger::info( LogComponent::AUTH, "User '{$user->get_display_name()}' does not have an account, auto-creation is enabled, creating account and logging in", null, $user->get_username() );
				$user->scouting_oidc_user_create();
				// Promote per-state redirect only after successful callback validation and right before login.
				$this->oidc_client->apply_redirect_for_state_to_session( $state );
				$user->scouting_oidc_user_login();
		} else {
			Logger::warning( LogComponent::AUTH, "User '{$user->get_display_name()}' does not have an account and auto-creation is disabled, redirecting to login page with an error message", null, $user->get_username() );
			ErrorHandler::redirect_to_login_error( 'error', __( 'Webmaster disabled creation of new accounts', 'scouting-openid-connect' ), 'disabled_auto_create' );
		}

		// Configured post-login redirects exit from the wp_login hook. Clean OIDC callback parameters for the default flow.
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Handles failed login.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message the error message.
	 * @return string the HTML for the error message or an empty string if the user is not logged in.
	 */
	public function scouting_oidc_auth_login_failed( string $message ): string {
		// Check if user is logged in.
		if ( ! is_login() ) {
			return $message;
		}

		// Check if error_description, hint, and message are set in the URL.
		if ( ! filter_has_var( INPUT_GET, 'error_description' ) || ! filter_has_var( INPUT_GET, 'hint' ) || ! filter_has_var( INPUT_GET, 'message' ) ) {
			return $message;
		}

		// All raw $_GET reads collected here.
		$error_description_raw = filter_input( INPUT_GET, 'error_description', FILTER_UNSAFE_RAW );
		$error_message_raw     = filter_input( INPUT_GET, 'message', FILTER_UNSAFE_RAW );
		$hint_raw              = filter_input( INPUT_GET, 'hint', FILTER_UNSAFE_RAW );

		// All parameters are sanitized as they may contain untrusted data.
		$error_description = is_string( $error_description_raw ) ? sanitize_text_field( wp_unslash( $error_description_raw ) ) : '';
		$error_message     = is_string( $error_message_raw ) ? sanitize_text_field( wp_unslash( $error_message_raw ) ) : '';
		$hint              = is_string( $hint_raw ) ? sanitize_text_field( wp_unslash( $hint_raw ) ) : '';

		// If the error equals `The user denied the request`, show a translated message.
		if ( 'The user denied the request' === $hint ) {
			Logger::info( LogComponent::AUTH, 'User cancelled the OIDC authentication request' );
			$hint = __( 'The user denied the request', 'scouting-openid-connect' );
		}

		// If $hint contains Details: then put it on a new line and make it bold.
		$details = __( 'Details:', 'scouting-openid-connect' );
		if ( strpos( $hint, $details ) !== false ) {
			$details = explode( $details, $hint );
			$error   = '<div id="login_error" class="notice notice-error"><p><strong>Error: </strong>';
			$error  .= esc_html( $details[0] );
			$error  .= '<br><strong>Details:</strong>';
			$error  .= esc_html( $details[1] );
			$error  .= '</p></div>';
			return $error;
		}

		Logger::error( LogComponent::AUTH, "User failed to login with OIDC: {$error_message} - {$hint}" );

		// Display the error message.
		return '<div id="login_error" class="notice notice-error"><p><strong>Error: </strong>' . esc_html( $hint ) . '</p></div>';
	}

	/**
	 * Redirects after login based on settings.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 Added the `$user_login` parameter.
	 * @since 1.2.0 Added support for a custom successful-login redirect.
	 *
	 * @param string $user_login the username of the user logging in.
	 */
	public function scouting_oidc_auth_login_redirect( string $user_login ): void {
		// If a post-login redirect was stored (from a shortcode redirect_back), honor it and exit early.
		$maybe_redirect = $this->oidc_client->get_and_clear_post_login_redirect_from_session();
		if ( is_string( $maybe_redirect ) && '' !== $maybe_redirect ) {
			$safe_redirect = wp_validate_redirect( $maybe_redirect, home_url() );
			wp_safe_redirect( $safe_redirect );
			exit;
		}

		$user = get_user_by( 'login', $user_login );
		if ( ! $user ) {
			return;
		}

		$is_scouting_oidc_user = get_user_meta( $user->ID, 'scouting_oidc_user', true );

		// If the user meta is not set, it will set it to 'false'.
		if ( '' === $is_scouting_oidc_user ) {
			update_user_meta( $user->ID, 'scouting_oidc_user', 'false' );
			$is_scouting_oidc_user = 'false';
		}

		// If redirection is enabled, skip redirect for users not marked as scouting OIDC (non-SOL users).
		if ( get_option( 'scouting_oidc_user_redirect' ) && 'false' === $is_scouting_oidc_user ) {
			return;
		}

		$redirect_setting = get_option( 'scouting_oidc_login_redirect' );
		if ( 'default' === $redirect_setting ) {
			return;
		} elseif ( 'dashboard' === $redirect_setting ) {
			wp_safe_redirect( admin_url() );
			exit;
		} elseif ( 'frontpage' === $redirect_setting ) {
			wp_safe_redirect( home_url() );
			exit;
		} elseif ( 'custom' === $redirect_setting ) {
			$custom_redirect = get_option( 'scouting_oidc_custom_redirect' );
			$safe_redirect   = wp_validate_redirect( $custom_redirect, home_url() );
			wp_safe_redirect( $safe_redirect );
			exit;
		} else {
			return;
		}
	}

	/**
	 * Redirects after logout based on settings.
	 *
	 * @since 1.0.0
	 * @since 2.1.0 Allowed configured external logout redirect hosts.
	 * @since 2.4.0 Added the `$user_id` parameter.
	 *
	 * @param int $user_id Optional. The logged-out user ID. Default 0.
	 */
	public function scouting_oidc_auth_logout_redirect( int $user_id = 0 ): void {
		$logout_url = esc_url_raw( $this->oidc_client->get_logout_url() );
		$this->oidc_client->clear_stored_id_token();

		$user = null;
		if ( $user_id > 0 ) {
			$found_user = get_user_by( 'ID', $user_id );
			if ( $found_user instanceof \WP_User ) {
				$user = $found_user;
			}
		}

		if ( ! $user ) {
			$current_user = wp_get_current_user();
			if ( $current_user instanceof \WP_User && $current_user->ID > 0 ) {
				$user = $current_user;
			}
		}

		$display_name   = $user instanceof \WP_User && '' !== $user->display_name ? $user->display_name : 'Unknown user';
		$log_user_id    = $user instanceof \WP_User && $user->ID > 0 ? $user->ID : null;
		$log_user_login = $user instanceof \WP_User && '' !== $user->user_login ? $user->user_login : null;

		Logger::info( LogComponent::USER, "User '{$display_name}' logged out", $log_user_id, $log_user_login );

		wp_safe_redirect( $logout_url );
		exit;
	}

	/**
	 * Returns the icon URL.
	 *
	 * @since 1.0.0
	 * @since 2.4.0 Added the `$icon_id` parameter.
	 *
	 * @param string $icon_id Optional. The HTML element ID for the icon. Default 'scouting-oidc-login-img'.
	 * @return string the HTML for the SVG icon or an empty string if the icon cannot be loaded.
	 */
	private function scouting_oidc_auth_icon( string $icon_id = 'scouting-oidc-login-img' ): string {
		// Define the path to the SVG file.
		$svg_file_path = SCOUTING_OIDC_PATH . 'assets/icon.svg';

		// Check if the file exists.
		if ( file_exists( $svg_file_path ) ) {
			// Get the contents of the SVG file.
			$wp_filesystem = new \WP_Filesystem_Direct( null );
			$svg_content   = $wp_filesystem->get_contents( $svg_file_path );

			// Modify the SVG tag to include additional attributes.
			$sanitized_icon_id = sanitize_html_class( $icon_id );
			$svg_attributes    = 'id="' . esc_attr( $sanitized_icon_id ) . '" class="scouting-oidc-login-img" style="width: 2.5rem; height: 2.5rem; margin-right: 10px;" role="img" aria-label="Scouting NL Logo"';
			$svg_content       = preg_replace( '/<svg([^>]+)>/', '<svg\1 ' . $svg_attributes . '>', $svg_content );

			// Return the SVG content.
			return $svg_content;
		}
		Logger::error( LogComponent::ASSETS, "Failed to load scouting SVG icon from path: {$svg_file_path}" );
		return '';
	}

	/**
	 * Returns the allowed SVG tags.
	 *
	 * @since 1.0.0
	 *
	 * @return array the array of allowed SVG tags.
	 */
	private function scouting_oidc_auth_icon_wp_kses_allowed_svg(): array {
		return array(
			'svg'   => array(
				'version'    => true,
				'xmlns'      => true,
				'viewbox'    => true,
				'id'         => true,
				'class'      => true,
				'style'      => true,
				'role'       => true,
				'aria-label' => true,
			),
			'title' => array(),
			'style' => array(),
			'g'     => array(
				'id' => true,
			),
			'path'  => array(
				'id'    => true,
				'class' => true,
				'd'     => true,
			),
		);
	}

	/**
	 * Returns the login URL.
	 *
	 * @since 1.0.0
	 * @since 2.4.0 Added the `$redirect_after_login` parameter.
	 *
	 * @param string|null $redirect_after_login Optional. URL to redirect to after login,
	 *                                          used for shortcode support. If null, default
	 *                                          redirect behavior applies.
	 * @return string the login URL.
	 */
	private function scouting_oidc_auth_login_url( ?string $redirect_after_login = null ): string {
		$response_type = 'code';
		$scopes        = array_map( 'sanitize_text_field', explode( ' ', get_option( 'scouting_oidc_scopes' ) ) );

		// Check if error_description, hint, and message are set in the URL.
		if ( filter_has_var( INPUT_GET, 'error_description' ) && filter_has_var( INPUT_GET, 'hint' ) ) {

			// All raw $_GET reads collected here.
			$error_description_raw = filter_input( INPUT_GET, 'error_description', FILTER_UNSAFE_RAW );
			$hint_raw              = filter_input( INPUT_GET, 'hint', FILTER_UNSAFE_RAW );

			// All parameters are sanitized as they may contain untrusted data.
			$error_description = is_string( $error_description_raw ) ? sanitize_text_field( wp_unslash( $error_description_raw ) ) : '';
			$hint              = is_string( $hint_raw ) ? sanitize_text_field( wp_unslash( $hint_raw ) ) : '';

			// If the error equals `init`, it means there was an error during the initialization of the login URL, so we log it and return a custom URL that indicates an initialization error with the hint as a parameter.
			if ( 'init' === $error_description ) {
				Logger::error( LogComponent::AUTH, "OIDC login URL builder returning init error: {$hint}" );
				return 'init_error:' . $hint;
			}
		}

		return $this->oidc_client->get_authentication_url( $response_type, $scopes, $redirect_after_login );
	}
}
