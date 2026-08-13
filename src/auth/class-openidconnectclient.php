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

require_once plugin_dir_path( __FILE__ ) . 'class-session.php';
require_once plugin_dir_path( __FILE__ ) . '../../src/utilities/class-errorhandler.php';
require_once plugin_dir_path( __FILE__ ) . '../../src/utilities/class-logger.php';

use ScoutingOIDC\Session;
use ScoutingOIDC\ErrorHandler;
use ScoutingOIDC\Logger;

/**
 * OpenIDConnectClient for Scouting OpenID Connect
 *
 * @category   Scouting OpenID Connect
 * @package    OpenIDConnectClient
 * @author     Job van Koeveringe <job.van.koeveringe@scouting.nl>
 * @copyright  2026 Scouting Nederland
 * @license    GPLv3
 */

/**
 * Handles Scouting OpenID Connect client operations.
 *
 * @since 1.0.0
 */
class OpenIDConnectClient {

	/**
	 * The client ID.
	 *
	 * @since 1.0.0
	 * @var string arbitrary id value.
	 */
	private $client_id;

	/**
	 * The client secret.
	 *
	 * @since 1.0.0
	 * @var string arbitrary secret value.
	 */
	private $client_secret;

	/**
	 * The redirect URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $redirect_url;

	/**
	 * The issuer.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $issuer;

	/**
	 * The scopes.
	 *
	 * @since 1.0.0
	 * @var array holds scopes.
	 */
	private $scopes = array();

	/**
	 * The well known data.
	 *
	 * @since 1.0.0
	 * @var array holds well-known data.
	 */
	private $well_known_data = array();

	/**
	 * The JWKS.
	 *
	 * @since 1.0.0
	 * @var array holds the JSON Web Key Set (JWKS).
	 */
	private $jwks = array();

	/**
	 * The tokens.
	 *
	 * @since 1.0.0
	 * @var array holds the tokens from the token endpoint.
	 */
	private $tokens = array();

	/**
	 * The session.
	 *
	 * @since 1.0.0
	 * @var Session holds the session.
	 */
	private $session;

	/**
	 * The logout redirect hosts.
	 *
	 * @since 2.4.0
	 * @var array<string> normalized hosts allowed for logout redirects.
	 */
	private static array $logout_redirect_hosts = array();

	/**
	 * The logout redirect hosts filter added.
	 *
	 * @since 2.4.0
	 * @var bool whether the allowed_redirect_hosts callback is already attached.
	 */
	private static bool $logout_redirect_hosts_filter_added = false;

	/**
	 * Initializes the OpenID Connect client.
	 *
	 * @since 1.0.0
	 *
	 * @param string $client_id The client ID.
	 * @param string $client_secret The client secret.
	 * @param string $redirect_uri The redirect URI.
	 * @param string $scouting_issuer The Scouting issuer URL.
	 */
	public function __construct( string $client_id, string $client_secret, string $redirect_uri, string $scouting_issuer ) {
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
		$this->redirect_url  = trailingslashit( $redirect_uri );
		$this->issuer        = $scouting_issuer;

		// Initialize session storage; create the cookie only when login begins.
		$this->session = new Session();
	}

	/**
	 * Generates the authentication URL.
	 *
	 * @since 1.0.0
	 * @since 2.2.0 Required PKCE S256 support for authorization requests.
	 * @since 2.4.0 Added the `$redirect_after_login` parameter.
	 *
	 * @param string      $response_type the response type.
	 * @param array       $scopes_array an array of scopes.
	 * @param string|null $redirect_after_login Optional. URL to redirect to after login,
	 *                                          used for shortcode support. Default null.
	 * @return string the authentication URL.
	 */
	public function get_authentication_url( string $response_type, array $scopes_array, ?string $redirect_after_login = null ): string {
		if ( ! $this->session->scouting_oidc_session_set_session_id() ) {
			Logger::critical( LogComponent::OIDC, 'A secure OIDC session cookie could not be created.' );
			ErrorHandler::redirect_to_login_error(
				'init',
				__( 'A secure login session could not be started. Please use HTTPS and try again.', 'scouting-openid-connect' ),
				'session_cookie_unavailable'
			);
		}

		Logger::debug( LogComponent::OIDC, 'Authentication URL generation started' );
		$this->get_well_known_data();
		$this->get_jwks_data();

		// Ensure PKCE with S256 is supported by the identity provider.
		if ( isset( $this->well_known_data->code_challenge_methods_supported ) && ! in_array( 'S256', $this->well_known_data->code_challenge_methods_supported, true ) ) {
			Logger::critical( LogComponent::OIDC, 'Identity provider does not support PKCE S256 code challenge method' );
			ErrorHandler::redirect_to_login_error( 'init', __( 'The identity provider does not support the required S256 code challenge method for PKCE.', 'scouting-openid-connect' ), 'pkce_not_supported' );
		}

		// Check if authorization_endpoint is available in well-known data.
		if ( empty( $this->well_known_data->authorization_endpoint ) ) {
			Logger::critical( LogComponent::OIDC, 'Authorization endpoint is not available in the well-known data' );
			ErrorHandler::redirect_to_login_error( 'init', __( 'The authorization_endpoint is not available in the well-known data.', 'scouting-openid-connect' ), 'authorization_endpoint_is_missing' );
		}

		// Set the scopes check if true or false.
		$invalid_scopes = $this->set_scopes( $scopes_array );
		if ( true !== $invalid_scopes ) {
			Logger::warning( LogComponent::OIDC, 'Configured scopes include unsupported values' );
			// Convert the invalid scopes array to a comma-separated string.
			$invalid_scopes_list = implode( ', ', $invalid_scopes );

			// Convert the supported scopes array to a comma-separated string.
			$supported_scopes_list = implode( ', ', $this->well_known_data->scopes_supported );

			// Generate a hint with the invalid scopes and the supported scopes.
			$hint = __( 'The following scopes are not supported:', 'scouting-openid-connect' ) . ' ' . $invalid_scopes_list . '. ' . __( 'The supported scopes are:', 'scouting-openid-connect' ) . ' ' . $supported_scopes_list;
			ErrorHandler::redirect_to_login_error( 'init', $hint, 'scopes_not_saved' );
		}

		// State essentially acts as a session key for OIDC.
		$state = $this->set_state( $this->generate_token( 32 ) );

		// Generate and store a nonce bound to this state so multiple pending logins do not overwrite each other.
		$nonce = $this->set_nonce_for_state( $state );

		// PKCE: generate and store a code verifier bound to this state, then derive the S256 challenge.
		$code_verifier = $this->generate_code_verifier();
		$this->set_code_verifier_for_state( $state, $code_verifier );
		$code_challenge = $this->generate_code_challenge( $code_verifier );

		// Persist the redirect URI so the token request reuses the exact same value.
		$this->session->scouting_oidc_session_set( 'scouting_oidc_redirect_uri', $this->redirect_url );

		$auth_params = array(
			'client_id'             => $this->client_id,
			'redirect_uri'          => $this->redirect_url,
			'scope'                 => implode( ' ', $this->scopes ),
			'response_type'         => $response_type,
			'nonce'                 => $nonce,
			'state'                 => $state,
			'code_challenge'        => $code_challenge,
			'code_challenge_method' => 'S256',
		);

		// If a specific post-login redirect was requested (e.g. shortcode redirect_back), store it keyed by state.
		if ( is_string( $redirect_after_login ) && '' !== $redirect_after_login ) {
			$redirect_after_login = esc_url_raw( $redirect_after_login );
			$this->set_redirect_for_state( $state, $redirect_after_login );
		}

		Logger::debug( LogComponent::OIDC, 'Authentication URL generated successfully' );

		return $this->well_known_data->authorization_endpoint . '?' . http_build_query( $auth_params, '', '&', PHP_QUERY_RFC1738 );
	}

	/**
	 * Retrieves the tokens from the token endpoint.
	 *
	 * @since 1.0.0
	 * @since 2.2.0 Added the `$state` parameter.
	 * @since 2.4.0 Bound token validation to state-specific nonce and PKCE values.
	 *
	 * @param string      $code The code from the authorization server.
	 * @param string|null $state Optional. The OIDC state value. Default null.
	 */
	public function retrieve_tokens( string $code, ?string $state = null ): void {
		Logger::debug( LogComponent::OIDC, 'ID token retrieval started' );
		$this->get_well_known_data();
		$this->get_jwks_data();

		// Reuse the redirect URI from the authorization request to avoid invalid_grant on the token endpoint.
		$saved_redirect = $this->session->scouting_oidc_session_get( 'scouting_oidc_redirect_uri' );
		if ( is_string( $saved_redirect ) && ! empty( $saved_redirect ) ) {
			$this->redirect_url = $saved_redirect;
		}

		// Check if token_endpoint is available in well-known data.
		if ( ! isset( $this->well_known_data->token_endpoint ) ) {
			Logger::error( LogComponent::OIDC, 'Token endpoint missing in well-known data' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The token_endpoint is not available in the well-known data.', 'scouting-openid-connect' ), 'token_endpoint_is_missing' );
		}

		// Set the grant type to authorization_code.
		$grant_type = 'authorization_code';

		// Fetch the stored PKCE verifier; state is mandatory to find the correct verifier.
		$code_verifier = ( null !== $state ) ? $this->get_code_verifier_for_state( $state ) : null;
		if ( empty( $code_verifier ) ) {
			Logger::error( LogComponent::OIDC, 'PKCE code_verifier missing from session for token exchange' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The code_verifier for PKCE is missing from the session.', 'scouting-openid-connect' ), 'code_verifier_missing' );
		}

		$data = array(
			'grant_type'    => $grant_type,
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'redirect_uri'  => $this->redirect_url,
			'code'          => $code,
			'code_verifier' => $code_verifier,
		);

		// Set the arguments for the POST request.
		$args = array(
			'body'        => $data,
			'timeout'     => 30,
			'redirection' => 5,
			'httpversion' => '2.0',
			'blocking'    => true,
			'headers'     => array(),
			'cookies'     => array(),
		);

		$response = wp_remote_post( $this->well_known_data->token_endpoint, $args );
		if ( is_wp_error( $response ) ) {
			Logger::log_wp_error( LogComponent::OIDC, LogLevel::ERROR, $response );
			ErrorHandler::redirect_to_login_error( 'error', $response->get_error_message(), 'get_tokens_failed' );
		}

		// Check if response code is 200 and response message is OK.
		$status_code      = wp_remote_retrieve_response_code( $response );
		$response_message = wp_remote_retrieve_response_message( $response );
		if ( 200 !== $status_code || 'OK' !== $response_message ) {
			$response_body = wp_remote_retrieve_body( $response );
			$body_decoded  = json_decode( $response_body, true );
			$error_detail  = $body_decoded['error_description'] ?? $body_decoded['error'] ?? $response_body;
			// translators: 1: HTTP status code returned by the token endpoint. 2: Error detail from the token endpoint response.
			$hint = sprintf( __( 'Token endpoint error %1$s: %2$s', 'scouting-openid-connect' ), $status_code, $error_detail );
			Logger::error( LogComponent::OIDC, "Token endpoint retrieval failed, HTTP status '{$status_code}' and message: {$response_body}" );
			ErrorHandler::redirect_to_login_error( 'error', $hint, 'get_tokens_failed' );
		}

		// Store the tokens.
		$this->tokens = json_decode( wp_remote_retrieve_body( $response ) );

		// Persist id_token so logout redirects can still include id_token_hint in later requests.
		$id_token = is_object( $this->tokens ) && property_exists( $this->tokens, 'id_token' ) ? $this->tokens->id_token : null;
		if ( is_string( $id_token ) && '' !== $id_token ) {
			$this->session->scouting_oidc_session_set( 'scouting_oidc_id_token', $id_token );
		}

		Logger::debug( LogComponent::OIDC, 'ID token retrieved successfully from token endpoint' );

		// Cleanup state and nonce.
		$this->unset_states_and_nonce();
	}

	/**
	 * Clears the state, nonce, and PKCE code verifiers from the session after token
	 * retrieval to prevent reuse and potential security issues.
	 *
	 * @since 1.0.0
	 */
	public function unset_states_and_nonce(): void {
		$this->session->scouting_oidc_session_delete( 'scouting_oidc_states' );
		$this->session->scouting_oidc_session_delete( 'scouting_oidc_nonces' );
		$this->session->scouting_oidc_session_delete( 'scouting_oidc_code_verifiers' );
		$this->session->scouting_oidc_session_delete( 'scouting_oidc_post_login_redirect' );

		Logger::debug( LogComponent::OIDC, 'OIDC state, nonce and PKCE verifier session data cleared' );
	}

	/**
	 * Validates the ID token and returns the payload.
	 *
	 * Performs full OIDC validation: structure check, algorithm guard, signature
	 * verification against the JWKS x5c certificate, and standard claim checks
	 * (iss, aud, exp, sub, nonce).
	 *
	 * @since 1.0.0
	 * @since 2.4.0 Added the `$stored_nonce` parameter.
	 *
	 * @param string $stored_nonce Optional. The nonce saved before retrieve_tokens cleared
	 *                             session state. Default empty string.
	 * @return array returns the validated payload.
	 */
	public function validate_tokens( string $stored_nonce = '' ): array {
		Logger::debug( LogComponent::OIDC, 'ID token validation started' );
		$this->get_well_known_data();
		$this->get_jwks_data();

		// Check if id_token is available in tokens.
		if ( ! isset( $this->tokens->id_token ) ) {
			Logger::error( LogComponent::OIDC, 'ID token missing from token response' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token is not available in the tokens.', 'scouting-openid-connect' ), 'id_token_is_missing' );
		}

		// Check if jwks is available.
		if ( empty( $this->jwks ) ) {
			Logger::error( LogComponent::OIDC, 'JWKS data missing during token validation' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The JSON Web Key Set (JWKS) is not available.', 'scouting-openid-connect' ), 'jwks_is_missing' );
		}

		// Validate JWT has exactly 3 segments before splitting.
		$parts = explode( '.', $this->tokens->id_token );
		if ( count( $parts ) !== 3 ) {
			Logger::error( LogComponent::OIDC, 'ID token has an invalid structure: expected 3 dot-separated segments, got ' . count( $parts ) );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token has an invalid structure.', 'scouting-openid-connect' ), 'malformed_token' );
		}

		[$header_encoded, $payload_encoded, $signature_encoded] = $parts;

		// Decode the header, payload and signature.
		$header    = json_decode( $this->base64_url_decode( $header_encoded ), true );
		$payload   = json_decode( $this->base64_url_decode( $payload_encoded ), true );
		$signature = $this->base64_url_decode( $signature_encoded );

		// Validate the algorithm header to prevent unexpected algorithm attacks.
		$expected_alg = 'RS256';
		if ( ! is_array( $header ) || ( $header['alg'] ?? '' ) !== $expected_alg ) {
			Logger::error( LogComponent::OIDC, 'ID token uses an unexpected algorithm: ' . ( $header['alg'] ?? 'none' ) );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token uses an unexpected signing algorithm.', 'scouting-openid-connect' ), 'invalid_alg' );
		}

		// Loop through the keys in the JSON Web Key Set (JWKS) to find the certificate chain (x5c) for the key ID (kid) specified in the header.
		$x5c = null;
		foreach ( $this->jwks->keys as $key ) {
			if ( $key->kid === $header['kid'] ) {
				$x5c = $key->x5c[0];
				break;
			}
		}

		// Check if the certificate chain (x5c) was found.
		if ( null === $x5c ) {
			Logger::error( LogComponent::OIDC, 'ID token validation failed: the certificate chain (x5c) for the key ID (kid) specified in the header was not found in JWKS' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The certificate chain (x5c) for the key ID (kid) specified in the header was not found.', 'scouting-openid-connect' ), 'jwks_is_missing' );
		}

		// Convert the certificate chain (x5c) to a public key certificate.
		$public_key_certificate = "-----BEGIN CERTIFICATE-----\n" . chunk_split( $x5c, 64, "\n" ) . '-----END CERTIFICATE-----';

		// Check if the signing certificate can be converted to a public key before verifying the signature.
		$public_key = openssl_pkey_get_public( $public_key_certificate );
		if ( false === $public_key ) {
			$openssl_error = openssl_error_string();
			$log_message   = 'ID token validation failed: unable to extract public key from signing certificate';
			if ( false !== $openssl_error ) {
				$log_message .= ' (' . $openssl_error . ')';
			}
			Logger::error( LogComponent::OIDC, $log_message );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The signing certificate used to validate the ID token is invalid or unsupported.', 'scouting-openid-connect' ), 'invalid_signing_certificate' );
		}

		// Check if the signature is valid.
		$signature_valid = openssl_verify( $header_encoded . '.' . $payload_encoded, $signature, $public_key, OPENSSL_ALGO_SHA256 );
		if ( 1 !== $signature_valid ) {
			Logger::error( LogComponent::OIDC, 'ID token signature validation failed' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The signature in the ID token is not valid.', 'scouting-openid-connect' ), 'invalid_signature' );
		}

		// Validate standard OIDC claims after signature is confirmed.
		if ( ! is_array( $payload ) ) {
			Logger::error( LogComponent::OIDC, 'ID token payload could not be decoded' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token payload could not be decoded.', 'scouting-openid-connect' ), 'malformed_token' );
		}

		// iss: must match the configured issuer.
		if ( ( $payload['iss'] ?? '' ) !== $this->issuer ) {
			Logger::error( LogComponent::OIDC, 'ID token issuer mismatch: expected ' . $this->issuer . ', got ' . ( $payload['iss'] ?? '(missing)' ) );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token was issued by an unexpected issuer.', 'scouting-openid-connect' ), 'invalid_iss' );
		}

		// aud: must contain the configured client ID.
		$audience = (array) ( $payload['aud'] ?? array() );
		if ( ! in_array( $this->client_id, $audience, true ) ) {
			Logger::error( LogComponent::OIDC, 'ID token audience does not include the configured client ID' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token was not issued for this client.', 'scouting-openid-connect' ), 'invalid_aud' );
		}

		// exp: token must not be expired.
		if ( ( $payload['exp'] ?? 0 ) < time() ) {
			Logger::error( LogComponent::OIDC, 'ID token has expired (exp=' . ( $payload['exp'] ?? 'missing' ) . ')' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token has expired.', 'scouting-openid-connect' ), 'token_expired' );
		}

		// iat: reject tokens that appear to be issued too far in the future (allow small clock skew).
		if ( ( $payload['iat'] ?? 0 ) > ( time() + 60 ) ) {
			Logger::error( LogComponent::OIDC, 'ID token has an invalid issued-at time in the future (iat=' . ( $payload['iat'] ?? 'missing' ) . ')' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token has an invalid issued-at timestamp.', 'scouting-openid-connect' ), 'invalid_iat' );
		}

		// sub: subject must be present.
		if ( empty( $payload['sub'] ) ) {
			Logger::error( LogComponent::OIDC, 'ID token is missing the sub claim' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token is missing the required sub claim.', 'scouting-openid-connect' ), 'missing_sub' );
		}

		// Validate the nonce claim against the value sent in the auth request.
		if ( empty( $stored_nonce ) ) {
			Logger::error( LogComponent::OIDC, 'ID token nonce validation failed: no stored nonce available in session state' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The login session is invalid or has expired.', 'scouting-openid-connect' ), 'missing_nonce' );
		}

		$token_nonce = $payload['nonce'] ?? '';
		if ( '' === $token_nonce ) {
			Logger::error( LogComponent::OIDC, 'ID token is missing the nonce claim' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token is missing the required nonce claim.', 'scouting-openid-connect' ), 'missing_token_nonce' );
		}

		if ( ! hash_equals( $stored_nonce, $token_nonce ) ) {
			Logger::error( LogComponent::OIDC, 'ID token nonce mismatch: the nonce in the token does not match the session nonce' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'The ID token nonce is invalid.', 'scouting-openid-connect' ), 'nonce_mismatch' );
		}

		Logger::debug( LogComponent::OIDC, 'ID token validation succeeded' );
		return $payload;
	}

	/**
	 * Retrieves user claims from the UserInfo endpoint.
	 *
	 * @since 2.5.0
	 *
	 * @param string $expected_subject the subject from the validated ID token.
	 * @return array returns the UserInfo claims.
	 */
	public function retrieve_user_info( string $expected_subject ): array {
		Logger::debug( LogComponent::OIDC, 'UserInfo retrieval started' );
		$this->get_well_known_data();

		$userinfo_endpoint = $this->well_known_data->userinfo_endpoint ?? null;
		if ( ! is_string( $userinfo_endpoint ) || ! filter_var( $userinfo_endpoint, FILTER_VALIDATE_URL ) ) {
			Logger::error( LogComponent::OIDC, 'UserInfo endpoint is missing or invalid in the well-known data' );
			ErrorHandler::redirect_to_login_error(
				'error',
				__( 'The userinfo_endpoint is not available in the well-known data.', 'scouting-openid-connect' ),
				'userinfo_endpoint_is_missing'
			);
		}

		$access_token = is_object( $this->tokens ) && property_exists( $this->tokens, 'access_token' )
			? $this->tokens->access_token
			: null;
		if ( ! is_string( $access_token ) || '' === $access_token ) {
			Logger::error( LogComponent::OIDC, 'Access token missing from token response' );
			ErrorHandler::redirect_to_login_error(
				'error',
				__( 'The access token is not available in the tokens.', 'scouting-openid-connect' ),
				'access_token_is_missing'
			);
		}

		$response = wp_safe_remote_get(
			$userinfo_endpoint,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'httpversion' => '2.0',
				'headers'     => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::log_wp_error( LogComponent::OIDC, LogLevel::ERROR, $response );
			ErrorHandler::redirect_to_login_error( 'error', $response->get_error_message(), 'get_userinfo_failed' );
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		if ( 200 !== $status_code ) {
			Logger::error( LogComponent::OIDC, "UserInfo retrieval failed with HTTP status '{$status_code}'" );
			ErrorHandler::redirect_to_login_error(
				'error',
				__( 'The UserInfo endpoint returned an unexpected response.', 'scouting-openid-connect' ),
				'get_userinfo_failed'
			);
		}

		$user_info = json_decode( $response_body, true );
		if ( ! is_array( $user_info ) || json_last_error() !== JSON_ERROR_NONE ) {
			Logger::error( LogComponent::OIDC, 'UserInfo endpoint returned an invalid JSON response' );
			ErrorHandler::redirect_to_login_error(
				'error',
				__( 'The UserInfo endpoint returned invalid user data.', 'scouting-openid-connect' ),
				'invalid_userinfo_response'
			);
		}

		$subject = $user_info['sub'] ?? null;
		if ( ! is_string( $subject ) || '' === $subject ) {
			Logger::error( LogComponent::OIDC, 'UserInfo response is missing the sub claim' );
			ErrorHandler::redirect_to_login_error(
				'error',
				__( 'The UserInfo response is missing the required sub claim.', 'scouting-openid-connect' ),
				'missing_userinfo_sub'
			);
		}

		if ( ! hash_equals( $expected_subject, $subject ) ) {
			Logger::error( LogComponent::OIDC, 'UserInfo subject does not match the validated ID token subject' );
			ErrorHandler::redirect_to_login_error(
				'error',
				__( 'The UserInfo response belongs to a different subject.', 'scouting-openid-connect' ),
				'userinfo_sub_mismatch'
			);
		}

		Logger::debug( LogComponent::OIDC, 'UserInfo retrieval succeeded' );
		return $user_info;
	}

	/**
	 * Gets the logout URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string returns the logout URL.
	 */
	public function get_logout_url(): string {
		$this->get_well_known_data();
		$this->get_jwks_data();

		// Check if end_session_endpoint is available in well-known data.
		if ( ! isset( $this->well_known_data->end_session_endpoint ) ) {
			Logger::warning( LogComponent::OIDC, 'While constructing logout URL, end_session_endpoint was missing from well-known data, falling back to home URL' );
			$logout_url = home_url();
		} else {
			$id_token = $this->get_id_token_for_logout();

			// Redirect to WordPress home URL if ID token is not available.
			if ( ! $id_token ) {
				Logger::warning( LogComponent::OIDC, 'While constructing logout URL, no id_token available, falling back to home URL' );
				$logout_url = home_url();
			} else {
				// add id_token_hint & client_id to the logout URL.
				$logout_params = array(
					'id_token_hint' => $id_token,
					'client_id'     => $this->client_id,
				);

				$logout_url = $this->well_known_data->end_session_endpoint . '?' . http_build_query( $logout_params, '', '&', PHP_QUERY_RFC1738 );
			}
		}

		// Make sure the external logout host is allowed for safe redirects.
		// wp_safe_redirect() checks the host against the 'allowed_redirect_hosts' filter,
		// so we add the logout host here to avoid blocking a trusted external logout URL.
		$host = wp_parse_url( $logout_url, PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host ) {
			$this->register_logout_redirect_host( $host );
		}

		return $logout_url;
	}

	/**
	 * Clears the stored ID token copy from session storage.
	 *
	 * @since 2.4.0
	 */
	public function clear_stored_id_token(): void {
		$this->session->scouting_oidc_session_delete( 'scouting_oidc_id_token' );
	}

	/**
	 * Gets an ID token suitable for OIDC logout from memory or session storage.
	 *
	 * @since 2.4.0
	 *
	 * @return string|null the id_token if available, otherwise null.
	 */
	private function get_id_token_for_logout(): ?string {
		// Prefer the in-memory token when available in this request.
		$in_memory_id_token = is_object( $this->tokens ) && property_exists( $this->tokens, 'id_token' ) ? $this->tokens->id_token : null;
		if ( is_string( $in_memory_id_token ) && '' !== $in_memory_id_token ) {
			return $in_memory_id_token;
		}

		// Fallback to the session copy for logout requests where tokens are not in memory anymore.
		$session_id_token = $this->session->scouting_oidc_session_get( 'scouting_oidc_id_token' );
		if ( is_string( $session_id_token ) && '' !== $session_id_token ) {
			return $session_id_token;
		}

		return null;
	}

	/**
	 * Registers a host for logout redirects and attaches the filter callback once per request.
	 *
	 * @since 2.4.0
	 *
	 * @param string $host the host to allow for redirects.
	 */
	private function register_logout_redirect_host( string $host ): void {
		$normalized_host = strtolower( trim( $host ) );

		if ( '' === $normalized_host ) {
			return;
		}

		if ( ! in_array( $normalized_host, self::$logout_redirect_hosts, true ) ) {
			self::$logout_redirect_hosts[] = $normalized_host;
		}

		if ( ! self::$logout_redirect_hosts_filter_added ) {
			add_filter( 'allowed_redirect_hosts', array( __CLASS__, 'scouting_oidc_filter_allowed_redirect_hosts' ) );
			self::$logout_redirect_hosts_filter_added = true;
		}
	}

	/**
	 * Extends allowed redirect hosts with normalized logout hosts.
	 *
	 * @since 2.4.0
	 *
	 * @param array $hosts existing allowed hosts.
	 * @return array updated allowed hosts.
	 */
	public static function scouting_oidc_filter_allowed_redirect_hosts( array $hosts ): array {
		$normalized_existing_hosts = array();

		foreach ( $hosts as $existing_host ) {
			if ( is_string( $existing_host ) && '' !== $existing_host ) {
				$normalized_existing_hosts[] = strtolower( $existing_host );
			}
		}

		foreach ( self::$logout_redirect_hosts as $logout_host ) {
			if ( ! in_array( $logout_host, $normalized_existing_hosts, true ) ) {
				$hosts[]                     = $logout_host;
				$normalized_existing_hosts[] = $logout_host;
			}
		}

		return $hosts;
	}

	/**
	 * Gets the well-known data from the issuer and stores it in the class property.
	 * Caches the well-known data in a transient for one hour to reduce requests to
	 * the issuer's .well-known endpoint.
	 *
	 * @since 1.0.0
	 */
	public function get_well_known_data(): void {
		// Define a transient key for caching the well-known data, scoped to the issuer
		// so that changing the issuer in settings does not serve stale cached data.
		$transient_key = 'scouting_oidc_wk_' . md5( $this->issuer );

		// Check if the well-known data already exists in the cache (transient).
		$well_known_data = get_transient( $transient_key );

		// If data exists in the transient, use it.
		if ( false !== $well_known_data ) {
			$this->well_known_data = $well_known_data;
			Logger::debug( LogComponent::OIDC, 'Well-known data loaded from transient cache' );
			return;
		}

		$well_known_config_url = $this->issuer . '/.well-known/openid-configuration';

		// Get the well-known configuration from the issuer.
		$response = wp_remote_get( $well_known_config_url );
		if ( is_wp_error( $response ) ) {
			Logger::log_wp_error( LogComponent::OIDC, LogLevel::ERROR, $response );
			ErrorHandler::redirect_to_login_error( 'init', $response->get_error_message(), 'get_well_known_data_failed' );
		} else {
			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 === $status_code ) {
				$this->well_known_data = json_decode( wp_remote_retrieve_body( $response ) );

				// Store the well-known data in a transient for 1 hour (3600 seconds).
				set_transient( $transient_key, $this->well_known_data, 3600 );
				Logger::debug( LogComponent::OIDC, 'Well-known data fetched and cached successfully' );
			} else {
				// Extract additional error information if available.
				$response_body = wp_remote_retrieve_body( $response );
				$error_details = ! empty( $response_body ) ? $response_body : __( 'No additional details provided.', 'scouting-openid-connect' );
				$hint          = __( 'When retrieving well-known data, the status code was:', 'scouting-openid-connect' ) . ' ' . $status_code . '.' . __( 'Details:', 'scouting-openid-connect' ) . ' ' . $error_details;
				Logger::error( LogComponent::OIDC, "Well-known data retrieval failed, HTTP status '{$status_code}' and message: {$response_body}" );
				ErrorHandler::redirect_to_login_error( 'init', $hint, 'unexpected_response' );
			}
		}
	}

	/**
	 * Gets the JSON Web Key Set (JWKS) from the jwks_uri in the well-known data and stores it in the class property.
	 * Caches the JWKS data in a transient for 1 hour to reduce the number of requests to the jwks_uri.
	 *
	 * @since 1.0.0
	 */
	public function get_jwks_data(): void {
		// Define a transient key for caching the JWKS data, scoped to the issuer
		// so that changing the issuer in settings does not serve stale cached keys.
		$transient_key = 'scouting_oidc_jwks_' . md5( $this->issuer );

		// Check if the JWKS data already exists in the cache (transient).
		$jwks_data = get_transient( $transient_key );

		// If data exists in the transient, use it.
		if ( false !== $jwks_data ) {
			$this->jwks = $jwks_data;
			Logger::debug( LogComponent::OIDC, 'JWKS data loaded from transient cache' );
			return;
		}

		// Check if jwks_uri is available in the well-known data.
		if ( empty( $this->well_known_data->jwks_uri ) ) {
			Logger::critical( LogComponent::OIDC, 'JWKS URI missing in well-known data' );
			ErrorHandler::redirect_to_login_error( 'init', __( 'The jwks_uri is not available in the well-known data.', 'scouting-openid-connect' ), 'jwks_uri_is_missing' );
		}

		// Check if jwks_uri is a valid URL.
		if ( ! filter_var( $this->well_known_data->jwks_uri, FILTER_VALIDATE_URL ) ) {
			Logger::critical( LogComponent::OIDC, 'JWKS URI in well-known data is not a valid URL' );
			$hint = __( 'The jwks_uri is not a valid URL.', 'scouting-openid-connect' ) . __( 'Details:', 'scouting-openid-connect' ) . ' ' . __( 'The jwks_uri is not valid:', 'scouting-openid-connect' ) . ' ' . $this->well_known_data->jwks_uri;
			ErrorHandler::redirect_to_login_error( 'init', $hint, 'jwks_uri_is_invalid' );
		}

		// Get the JSON Web Key Set (JWKS) from the jwks_uri.
		$response = wp_remote_get( $this->well_known_data->jwks_uri );
		if ( is_wp_error( $response ) ) {
			Logger::log_wp_error( LogComponent::OIDC, LogLevel::ERROR, $response );
			ErrorHandler::redirect_to_login_error( 'init', $response->get_error_message(), 'get_jwks_data_failed' );
		} else {
			$status_code = wp_remote_retrieve_response_code( $response );

			if ( 200 === $status_code ) {
				$this->jwks = json_decode( wp_remote_retrieve_body( $response ) );

				// Store the JWKS data in a transient for 1 hour (3600 seconds).
				set_transient( $transient_key, $this->jwks, 3600 );
				Logger::debug( LogComponent::OIDC, 'JWKS data fetched and cached successfully' );
			} else {
				// Extract additional error information if available.
				$response_body = wp_remote_retrieve_body( $response );
				$error_details = ! empty( $response_body ) ? $response_body : __( 'No additional details provided.', 'scouting-openid-connect' );
				$hint          = __( 'When retrieving JWKS data, the status code was:', 'scouting-openid-connect' ) . ' ' . $status_code . '.' . __( 'Details:', 'scouting-openid-connect' ) . ' ' . $error_details;
				Logger::error( LogComponent::OIDC, "JWKS data retrieval failed, HTTP status '{$status_code}' and message: {$response_body}" );
				ErrorHandler::redirect_to_login_error( 'init', $hint, 'unexpected_response' );
			}
		}
	}

	/**
	 * Sets the scopes.
	 *
	 * @since 1.0.0
	 *
	 * @param array $scopes_array an array of scopes.
	 * @return bool|array true if the scopes are set, or an array of invalid scopes if any.
	 */
	private function set_scopes( array $scopes_array ): bool|array {
		// Check if $scopes_array is not a valid array or is empty.
		if ( ! is_array( $scopes_array ) || empty( $scopes_array ) ) {
			return false;
		}

		// Check if scopes are allowed by the server using array_diff.
		if ( isset( $this->well_known_data->scopes_supported ) ) {
			// Get the invalid scopes (those not in the supported scopes).
			$invalid_scopes = array_diff( $scopes_array, $this->well_known_data->scopes_supported );

			// If there are any invalid scopes, return the list of invalid scopes.
			if ( ! empty( $invalid_scopes ) ) {
				return $invalid_scopes;
			}
		}

		// Set the scopes.
		$this->scopes = $scopes_array;
		return true;
	}

	/**
	 * Generates cryptographically secure random bytes and converts failures into a controlled auth error.
	 *
	 * @since 2.4.0
	 *
	 * @param int $length the number of bytes to generate.
	 * @return string the random bytes.
	 */
	private function generate_random_bytes( int $length ): string {
		try {
			return random_bytes( $length );
		} catch ( \Exception $e ) {
			Logger::error( LogComponent::OIDC, 'Failed to generate random bytes.' );
			ErrorHandler::redirect_to_login_error(
				'init',
				__( 'Authentication is temporarily unavailable. Please try again later.', 'scouting-openid-connect' ),
				'random_bytes_failed'
			);
		}
	}

	/**
	 * Generates a cryptographically secure random token.
	 *
	 * @since 1.0.0
	 *
	 * @param int $length the length of the token (characters).
	 * @return string the token.
	 */
	private function generate_token( int $length ): string {
		return substr( bin2hex( $this->generate_random_bytes( (int) ceil( $length / 2 ) ) ), 0, $length );
	}

	/**
	 * Generates a PKCE code_verifier.
	 *
	 * @since 2.2.0
	 *
	 * @param int $length Optional. Desired length between 43 and 128 characters. Default 64.
	 * @return string the code verifier.
	 */
	private function generate_code_verifier( int $length = 64 ): string {
		$min_length = 43;
		$max_length = 128;
		$length     = max( $min_length, min( $length, $max_length ) );

		$verifier        = '';
		$verifier_length = strlen( $verifier );
		while ( $verifier_length < $length ) {
			$verifier       .= $this->base64_url_encode( $this->generate_random_bytes( 32 ) );
			$verifier_length = strlen( $verifier );
		}

		return substr( $verifier, 0, $length );
	}

	/**
	 * Generates a PKCE code_challenge from the verifier.
	 *
	 * @since 2.2.0
	 *
	 * @param string $code_verifier the code verifier.
	 * @return string the code challenge.
	 */
	private function generate_code_challenge( string $code_verifier ): string {
		return $this->base64_url_encode( hash( 'sha256', $code_verifier, true ) );
	}

	/**
	 * Generates and stores a cryptographically secure random nonce keyed by state.
	 *
	 * @since 2.4.0
	 *
	 * @param string $state the OIDC state value.
	 * @return string the nonce.
	 */
	private function set_nonce_for_state( string $state ): string {
		$nonces = $this->session->scouting_oidc_session_get( 'scouting_oidc_nonces' ) ?? array();
		if ( ! is_array( $nonces ) ) {
			$nonces = array();
		}

		$nonce            = bin2hex( $this->generate_random_bytes( 16 ) );
		$nonces[ $state ] = $nonce;
		$this->session->scouting_oidc_session_set( 'scouting_oidc_nonces', $nonces );

		return $nonce;
	}

	/**
	 * Gets the stored nonce for the given state from the session.
	 *
	 * @since 2.4.0
	 *
	 * @param string $state the OIDC state value.
	 * @return string the nonce from the session or an empty string.
	 */
	public function get_nonce_for_state( string $state ): string {
		$nonces = $this->session->scouting_oidc_session_get( 'scouting_oidc_nonces' );
		if ( is_array( $nonces ) ) {
			$nonce = $nonces[ $state ] ?? null;
			if ( is_string( $nonce ) && '' !== $nonce ) {
				return $nonce;
			}
		}

		return '';
	}

	/**
	 * Adds a state to the stored array of states.
	 *
	 * @since 1.0.0
	 *
	 * @param string $state the state to store.
	 * @return string the state.
	 */
	private function set_state( string $state ): string {
		// Retrieve the current array of states, or initialize as empty.
		$states = $this->session->scouting_oidc_session_get( 'scouting_oidc_states' ) ?? array();

		// Ensure $states is an array (initialize as an empty array if it's null or not an array).
		if ( ! is_array( $states ) ) {
			$states = array();
		}

		// Add the new state to the array.
		$states[] = $state;

		// Store the updated array back in the session.
		$this->session->scouting_oidc_session_set( 'scouting_oidc_states', $states );

		return $state;
	}

	/**
	 * Stores the PKCE code_verifier in the session, keyed by state.
	 *
	 * @since 2.2.0
	 *
	 * @param string $state the OIDC state value.
	 * @param string $code_verifier the generated code verifier.
	 * @return string the stored code verifier.
	 */
	private function set_code_verifier_for_state( string $state, string $code_verifier ): string {
		$verifiers = $this->session->scouting_oidc_session_get( 'scouting_oidc_code_verifiers' ) ?? array();
		if ( ! is_array( $verifiers ) ) {
			$verifiers = array();
		}
		$verifiers[ $state ] = $code_verifier;
		$this->session->scouting_oidc_session_set( 'scouting_oidc_code_verifiers', $verifiers );

		return $code_verifier;
	}

	/**
	 * Stores a post-login redirect target keyed by state in the session.
	 *
	 * @since 2.4.0
	 *
	 * @param string $state the OIDC state value.
	 * @param string $redirect_url the URL to redirect to after login is complete.
	 */
	private function set_redirect_for_state( string $state, string $redirect_url ): void {
		$redirects = $this->session->scouting_oidc_session_get( 'scouting_oidc_redirects' ) ?? array();
		if ( ! is_array( $redirects ) ) {
			$redirects = array();
		}

		$redirects[ $state ] = $redirect_url;
		$this->session->scouting_oidc_session_set( 'scouting_oidc_redirects', $redirects );
	}

	/**
	 * Retrieves the stored redirect for a given state.
	 *
	 * @since 2.4.0
	 *
	 * @param string $state the OIDC state value.
	 * @return string|null the redirect URL if found, or null if not found or invalid.
	 */
	public function get_redirect_for_state( string $state ): ?string {
		$redirects = $this->session->scouting_oidc_session_get( 'scouting_oidc_redirects' );
		if ( ! is_array( $redirects ) ) {
			return null;
		}

		$redirect = $redirects[ $state ] ?? null;
		return is_string( $redirect ) && '' !== $redirect ? $redirect : null;
	}

	/**
	 * Removes the stored redirect for a given state.
	 *
	 * @since 2.4.0
	 *
	 * @param string $state the OIDC state value.
	 */
	private function delete_redirect_for_state( string $state ): void {
		$redirects = $this->session->scouting_oidc_session_get( 'scouting_oidc_redirects' ) ?? array();
		if ( ! is_array( $redirects ) ) {
			return;
		}

		if ( array_key_exists( $state, $redirects ) ) {
			unset( $redirects[ $state ] );
			$this->session->scouting_oidc_session_set( 'scouting_oidc_redirects', $redirects );
		}
	}

	/**
	 * Copies a per-state redirect to the post-login session and removes the per-state entry.
	 *
	 * The value is stored in this plugin's transient-backed session (1 hour TTL), so it may survive
	 * across requests until consumed by `get_and_clear_post_login_redirect_from_session()` or expiration.
	 *
	 * @since 2.4.0
	 *
	 * @param string $state the OIDC state value.
	 */
	public function apply_redirect_for_state_to_session( string $state ): void {
		$redirect = $this->get_redirect_for_state( $state );
		if ( null !== $redirect ) {
			$this->session->scouting_oidc_session_set( 'scouting_oidc_post_login_redirect', $redirect );
			$this->delete_redirect_for_state( $state );
		}

		if ( ! $this->session->scouting_oidc_session_regenerate_id() ) {
			Logger::critical( LogComponent::OIDC, 'A secure OIDC session cookie could not be created.' );
			ErrorHandler::redirect_to_login_error(
				'init',
				__( 'A secure login session could not be started. Please use HTTPS and try again.', 'scouting-openid-connect' ),
				'session_cookie_unavailable'
			);
		}
	}

	/**
	 * Retrieves and clears the post-login redirect stored in session.
	 *
	 * @since 2.4.0
	 *
	 * @return string|null the redirect URL if found, or null if not found or invalid.
	 */
	public function get_and_clear_post_login_redirect_from_session(): ?string {
		$redirect = $this->session->scouting_oidc_session_get( 'scouting_oidc_post_login_redirect' );
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			return null;
		}
		$this->session->scouting_oidc_session_delete( 'scouting_oidc_post_login_redirect' );
		return esc_url_raw( $redirect );
	}

	/**
	 * Gets the stored PKCE code_verifier for a given state from the session.
	 *
	 * @since 2.2.0
	 *
	 * @param string $state the OIDC state value.
	 * @return string|null String value or null.
	 */
	private function get_code_verifier_for_state( string $state ): ?string {
		$verifiers = $this->session->scouting_oidc_session_get( 'scouting_oidc_code_verifiers' );
		if ( ! is_array( $verifiers ) ) {
			return null;
		}

		$code_verifier = $verifiers[ $state ] ?? null;
		return is_string( $code_verifier ) ? $code_verifier : null;
	}

	/**
	 * Checks if a specific state exists in the stored array.
	 *
	 * @since 1.0.0
	 *
	 * @param string $state The state to search for.
	 * @return bool True if the state exists, false otherwise.
	 */
	public function has_state( string $state ): bool {
		$states = $this->session->scouting_oidc_session_get( 'scouting_oidc_states' ) ?? array();

		// Ensure $states is an array.
		if ( ! is_array( $states ) ) {
			$states = array();
		}

		return in_array( $state, $states, true );
	}

	/**
	 * Encodes data as base64url without padding.
	 *
	 * @since 2.2.0
	 *
	 * @param string $input The input value.
	 * @return string String value.
	 */
	private function base64_url_encode( string $input ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- OIDC base64url.
		return rtrim( strtr( base64_encode( $input ), '+/', '-_' ), '=' );
	}

	/**
	 * Decodes a base64url encoded string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $input The input value.
	 * @return string|false the decoded string or false on failure.
	 */
	private function base64_url_decode( string $input ): string|false {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- OIDC base64url.
		return base64_decode( strtr( $input, '-_', '+/' ) );
	}
}
