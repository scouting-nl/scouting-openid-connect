<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . 'Session.php';
require_once plugin_dir_path(__FILE__) . '../../src/utilities/ErrorHandler.php';

use ScoutingOIDC\Session;
use ScoutingOIDC\ErrorHandler;

/**
 * OpenIDConnectClient for Scouting OpenID Connect
 *
 * @category   Scouting OpenID Connect
 * @package    OpenIDConnectClient
 * @author     Job van Koeveringe <job.van.koeveringe@scouting.nl>
 * @copyright  2026 Scouting Nederland
 * @license    GPLv3
 *
 */

/**
 * OpenIDConnectClient for Scouting OIDC
 */
class OpenIDConnectClient
{
    /**
     * @var string arbitrary id value
     */
    private $clientID;

    /**
     * @var string arbitrary secret value
     */
    private $clientSecret;

    /**
     * @var string
     */
    private $redirectURL;

    /**
     * @var string
     */
    private $issuer;

    /**
     * @var array holds scopes
     */
    private $scopes = [];

    /**
     * @var array holds well-known data
     */
    private $wellKnownData = [];

    /**
    * @var array holds the JSON Web Key Set (JWKS) 
    */
   private $jwks = [];

    /**
     * @var array holds the tokens from the token endpoint
     */
    private $tokens = [];

    /**
     * @var Session holds the session
     */
    private $session;

    /**
     * OpenIDConnectClient constructor
     * 
     * @param string $client_id
     * @param string $client_secret
     * @param string $redirect_uri
     * @param string $scouting_issuer
     */
    public function __construct(string $client_id, string $client_secret, string $redirect_uri, string $scouting_issuer) {
        $this->clientID = $client_id;
        $this->clientSecret = $client_secret;
        $this->redirectURL = trailingslashit($redirect_uri);
        $this->issuer = $scouting_issuer;

        // Load session to store tokens if needed
        $this->session = new Session();
        $this->session->scouting_oidc_session_set_session_id();
    }

    /**
     * Generates the authentication URL
     * 
     * @param string $response_type the response type
     * @param array $scopes_array an array of scopes
     * @return string the authentication URL
     */
    public function getAuthenticationURL(string $response_type, array $scopes_array): string {
        $this->getWellKnownData();
        $this->getJWKSData();

        // Ensure PKCE with S256 is supported by the identity provider
        if (isset($this->wellKnownData->code_challenge_methods_supported) && !in_array('S256', $this->wellKnownData->code_challenge_methods_supported, true)) {
            ErrorHandler::redirect_to_login_error('init', __('The identity provider does not support the required S256 code challenge method for PKCE.', 'scouting-openid-connect'), 'pkce_not_supported');
        }

        // Check if authorization_endpoint is available in well-known data
        if (empty($this->wellKnownData->authorization_endpoint)) {
            ErrorHandler::redirect_to_login_error('init', __('The authorization_endpoint is not available in the well-known data.', 'scouting-openid-connect'), 'authorization_endpoint_is_missing');
        }

        // Set the scopes check if true or false
        $invalid_scopes  = $this->setScopes($scopes_array);
        if ($invalid_scopes !== true) {
            // Convert the invalid scopes array to a comma-separated string
            $invalid_scopes_list = implode(', ', $invalid_scopes);
            
            // Convert the supported scopes array to a comma-separated string
            $supported_scopes_list = implode(', ', $this->wellKnownData->scopes_supported);

            // Generate a hint with the invalid scopes and the supported scopes
            $hint = __('The following scopes are not supported:', 'scouting-openid-connect') . " " . $invalid_scopes_list . '. ' . __('The supported scopes are:', 'scouting-openid-connect') . " " . $supported_scopes_list;
            ErrorHandler::redirect_to_login_error('init', $hint, 'scopes_not_saved');
        }

        // Generate and store a nonce in the session
        $nonce = $this->setNonce();

        // State essentially acts as a session key for OIDC
        $state = $this->setState($this->generateToken(32));

        // PKCE: generate and store a code verifier bound to this state, then derive the S256 challenge
        $code_verifier = $this->generateCodeVerifier();
        $this->setCodeVerifierForState($state, $code_verifier);
        $code_challenge = $this->generateCodeChallenge($code_verifier);

        // Persist the redirect URI so the token request reuses the exact same value
        $this->session->scouting_oidc_session_set('scouting_oidc_redirect_uri', $this->redirectURL);

        $auth_params = [
            'client_id' => $this->clientID,
            'redirect_uri' => $this->redirectURL,
            'scope' => implode(' ', $this->scopes),
            'response_type' => $response_type,
            'nonce' => $nonce,
            'state' => $state,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256',
        ];

        return $this->wellKnownData->authorization_endpoint . '?' . http_build_query($auth_params, '', '&', PHP_QUERY_RFC1738);
    }

    /**
     * Retrieves the tokens from the token endpoint
     * 
     * @param string $code the code from the authorization server
     */
    public function retrieveTokens(string $code, ?string $state = null): void {
        $this->getWellKnownData();
        $this->getJWKSData();

        // Reuse the redirect URI from the authorization request to avoid invalid_grant on the token endpoint
        $saved_redirect = $this->session->scouting_oidc_session_get('scouting_oidc_redirect_uri');
        if (is_string($saved_redirect) && !empty($saved_redirect)) {
            $this->redirectURL = $saved_redirect;
        }

        // Check if token_endpoint is available in well-known data
        if (!isset($this->wellKnownData->token_endpoint)) {
            ErrorHandler::redirect_to_login_error('error', __('The token_endpoint is not available in the well-known data.', 'scouting-openid-connect'), 'token_endpoint_is_missing');
        }

        // Set the grant type to authorization_code
        $grant_type = 'authorization_code';

        // Fetch the stored PKCE verifier; state is mandatory to find the correct verifier
        $code_verifier = ($state !== null) ? $this->getCodeVerifierForState($state) : null;
        if (empty($code_verifier)) {
            ErrorHandler::redirect_to_login_error('error', __('The code_verifier for PKCE is missing from the session.', 'scouting-openid-connect'), 'code_verifier_missing');
        }

        $data = array(
            'grant_type' => $grant_type,
            'client_id' => $this->clientID,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectURL,
            'code' => $code,
            'code_verifier' => $code_verifier
       );

        // Set the arguments for the POST request
        $args = array(
            'body'        => $data,   // Data to be sent in the body of the request
            'timeout'     => 30,      // Timeout in seconds
            'redirection' => 5,       // Number of redirects allowed
            'httpversion' => '2.0',   // HTTP version to use
            'blocking'    => true,    // Whether to block until the request is complete
            'headers'     => array(), // Headers to include in the request
            'cookies'     => array()  // Cookies to include in the request
       );

        $response = wp_remote_post($this->wellKnownData->token_endpoint, $args);
        if (is_wp_error($response)) {
            ErrorHandler::redirect_to_login_error('error', $response->get_error_message(), 'get_tokens_failed');
        } 
        
        // Check if response code is 200 and response message is OK
        $status_code = wp_remote_retrieve_response_code($response);
        $response_message = wp_remote_retrieve_response_message($response);
        if ($status_code !== 200 || $response_message !== 'OK') {
            $body_raw = wp_remote_retrieve_body($response);
            $body_decoded = json_decode($body_raw, true);
            $error_detail = $body_decoded['error_description'] ?? $body_decoded['error'] ?? $body_raw;
            // translators: 1: HTTP status code returned by the token endpoint. 2: Error detail from the token endpoint response.
            $hint = sprintf(__('Token endpoint error %1$s: %2$s', 'scouting-openid-connect'), $status_code, $error_detail);
            ErrorHandler::redirect_to_login_error('error', $hint, 'get_tokens_failed');
        }

        // Store the tokens
        $this->tokens = json_decode(wp_remote_retrieve_body($response));

        // Cleanup state and nonce
        $this->unsetStatesAndNonce();
    }

    /**
     * Function to unset the state and nonce
     */
    public function unsetStatesAndNonce(): void {
        $this->session->scouting_oidc_session_delete('scouting_oidc_states');
        $this->session->scouting_oidc_session_delete('scouting_oidc_nonce');
        $this->session->scouting_oidc_session_delete('scouting_oidc_code_verifiers');
    }

    /**
     * Validates the ID token and returns the payload
     * 
     * @return array returns the payload 
     */
    public function validateTokens(): array {
        $this->getWellKnownData();
        $this->getJWKSData();

        // Check if id_token is available in tokens
        if (!isset($this->tokens->id_token)) {
            ErrorHandler::redirect_to_login_error('error', __('The ID token is not available in the tokens.', 'scouting-openid-connect'), 'id_token_is_missing');
        }

        // Check if jwks is available
        if (empty($this->jwks)) {
            ErrorHandler::redirect_to_login_error('error', __('The JSON Web Key Set (JWKS) is not available.', 'scouting-openid-connect'), 'jwks_is_missing');
        }

        // Split the token into header, payload and signature
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = explode('.', $this->tokens->id_token);
        
        // Decode the header, payload and signature
        $header = json_decode($this->base64UrlDecode($headerEncoded), true);
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);
        $signature = $this->base64UrlDecode($signatureEncoded);

        // Loop through the keys in the JSON Web Key Set (JWKS) to find the certificate chain (x5c) for the key ID (kid) specified in the header
        $x5c = null;
        foreach ($this->jwks->keys as $key) {
            if ($key->kid === $header['kid']) {
                $x5c = $key->x5c[0];
                break;
            }
        }

        // Check if the certificate chain (x5c) was found
        if ($x5c === null) {
            ErrorHandler::redirect_to_login_error('error', __('The certificate chain (x5c) for the key ID (kid) specified in the header was not found.', 'scouting-openid-connect'), 'jwks_is_missing');
        }

        // Convert the certificate chain (x5c) to a public key certificate
        $public_key_certificate = "-----BEGIN CERTIFICATE-----\n" . chunk_split($x5c, 64, "\n") . "-----END CERTIFICATE-----";

        // Check if the signature is valid
        $publicKey = openssl_pkey_get_public($public_key_certificate);
        $signatureValid = openssl_verify($headerEncoded . '.' . $payloadEncoded, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($signatureValid !== 1) {
            ErrorHandler::redirect_to_login_error('error', __('The signature in the ID token is not valid.', 'scouting-openid-connect'), 'jwks_is_missing');
        }
        else {
            return $payload;
        }
    }

    /**
     * Gets the logout URL
     * 
     * @return string returns the logout URL
     */
    public function getLogoutUrl(): string {
        $this->getWellKnownData();
        $this->getJWKSData();

        // Check if end_session_endpoint is available in well-known data
        if (!isset($this->wellKnownData->end_session_endpoint)) {
            return home_url();
        }

        // Ensure tokens is an object and id_token is available
        $id_token = is_object($this->tokens) && property_exists($this->tokens, 'id_token') ? $this->tokens->id_token : null;

        // Redirect to WordPress home URL if ID token is not available
        if (!$id_token) {
            return home_url();
        }

        // add id_token_hint & client_id to the logout URL
        $logout_params = [
            'id_token_hint' => $id_token,
            'client_id' => $this->clientID,
        ];
        
        return $this->wellKnownData->end_session_endpoint . '?' . http_build_query($logout_params, '', '&', PHP_QUERY_RFC1738);
    }

    /**
     * Gets anything that we need configuration wise including endpoints, and other values
     */
    public function getWellKnownData(): void {
        // Define a transient key for caching the well-known data
        $transient_key = 'scouting_oidc_well_known_data';

        // Check if the well-known data already exists in the cache (transient)
        $well_known_data = get_transient($transient_key);

        // If data exists in the transient, use it
        if ($well_known_data !== false) {
            $this->wellKnownData = $well_known_data;
            return; // Exit the method early as the data is already cached
        }

        $well_known_config_url = $this->issuer . '/.well-known/openid-configuration';

        // Get the well-known configuration from the issuer
        $response = wp_remote_get($well_known_config_url);
        if (is_wp_error($response)) {
            ErrorHandler::redirect_to_login_error('init', $response->get_error_message(), 'get_well_known_data_failed');
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                $this->wellKnownData = json_decode(wp_remote_retrieve_body($response));

                // Store the well-known data in a transient for 1 hour (3600 seconds)
                set_transient($transient_key, $this->wellKnownData, 3600);
            } else {
                // Extract additional error information if available
                $response_body = wp_remote_retrieve_body($response);
                $error_details = !empty($response_body) ? $response_body : __('No additional details provided.', 'scouting-openid-connect');
                $hint = __('When retrieving well-known data, the status code was:', 'scouting-openid-connect') . " " . $status_code . "." . __('Details:', 'scouting-openid-connect') . " " . $error_details;
                ErrorHandler::redirect_to_login_error('init', $hint, 'unexpected_response');
            }
        }
    }

    /**
     * Gets the JSON Web Key Set (JWKS) from the jwks_uri
     */
    public function getJWKSData(): void {
        // Define a transient key for caching the JWKS data
        $transient_key = 'scouting_oidc_jwks_data';
    
        // Check if the JWKS data already exists in the cache (transient)
        $jwks_data = get_transient($transient_key);
    
        // If data exists in the transient, use it
        if ($jwks_data !== false) {
            $this->jwks = $jwks_data;
            return; // Exit the method early as the data is already cached
        }
    
        // Check if jwks_uri is available in the well-known data
        if (empty($this->wellKnownData->jwks_uri)) {
            ErrorHandler::redirect_to_login_error('init', __('The jwks_uri is not available in the well-known data.', 'scouting-openid-connect'), 'jwks_uri_is_missing');
        }
    
        // Check if jwks_uri is a valid URL
        if (!filter_var($this->wellKnownData->jwks_uri, FILTER_VALIDATE_URL)) {
            $hint = __('The jwks_uri is not a valid URL.', 'scouting-openid-connect') . __('Details:', 'scouting-openid-connect') . " " . __('The jwks_uri is not valid:', 'scouting-openid-connect') . " " . $this->wellKnownData->jwks_uri;
            ErrorHandler::redirect_to_login_error('init', $hint, 'jwks_uri_is_invalid');
        }
    
        // Get the JSON Web Key Set (JWKS) from the jwks_uri
        $response = wp_remote_get($this->wellKnownData->jwks_uri);
        if (is_wp_error($response)) {
            ErrorHandler::redirect_to_login_error('init', $response->get_error_message(), 'get_jwks_data_failed');
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
        
            if ($status_code === 200) {
                $this->jwks = json_decode(wp_remote_retrieve_body($response));

                // Store the JWKS data in a transient for 1 hour (3600 seconds)
                set_transient($transient_key, $this->jwks, 3600); // Cache for 1 hour
            } else {
                // Extract additional error information if available
                $response_body = wp_remote_retrieve_body($response);
                $error_details = !empty($response_body) ? $response_body : __('No additional details provided.', 'scouting-openid-connect');
                $hint = __('When retrieving JWKS data, the status code was:', 'scouting-openid-connect') . " " . $status_code . "." . __('Details:', 'scouting-openid-connect') . " " . $error_details;
                ErrorHandler::redirect_to_login_error('init', $hint, 'unexpected_response');
            }
        }
    }

    /**
     * Sets the scopes
     * 
     * @param array $scopes_array an array of scopes
     * @return bool|array true if the scopes are set, or an array of invalid scopes if any
     */
    private function setScopes(array $scopes_array): bool|array {
        // Check if $scopes_array is not a valid array or is empty
        if (!is_array($scopes_array) || empty($scopes_array)) {
            return false;
        }

        // Check if scopes are allowed by the server using array_diff
        if (isset($this->wellKnownData->scopes_supported)) {
            // Get the invalid scopes (those not in the supported scopes)
            $invalid_scopes = array_diff($scopes_array, $this->wellKnownData->scopes_supported);
            
            // If there are any invalid scopes, return the list of invalid scopes
            if (!empty($invalid_scopes)) {
                return $invalid_scopes;
            }
        }

        // Set the scopes
        $this->scopes = $scopes_array;
        return true;
    }

    /**
     * Generates a random token
     *
     * @param int $length the length of the token
     * @return string the token
     */
    private function generateToken(int $length): string {
        //set up random characters
        $chars='1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
        // Get the length of the random characters
        $char_len = strlen($chars)-1;
        //store output
        $output = '';
        //iterate over $chars
        while (strlen($output) < $length) {
            /* get random characters and append to output till the length of the output 
             is greater than the length provided */
            $output .= $chars[wp_rand(0, $char_len)];
        }
        //return the result
        return $output;
    }

    /**
     * Generates a PKCE code_verifier
     *
     * @param int $length desired length between 43 and 128 characters
     * @return string the code verifier
     */
    private function generateCodeVerifier(int $length = 64): string {
        $min_length = 43;
        $max_length = 128;
        $length = max($min_length, min($length, $max_length));

        $verifier = '';
        while (strlen($verifier) < $length) {
            $verifier .= $this->base64UrlEncode(random_bytes(32));
        }

        return substr($verifier, 0, $length);
    }

    /**
     * Generates a PKCE code_challenge from the verifier
     *
     * @param string $code_verifier the code verifier
     * @return string the code challenge
     */
    private function generateCodeChallenge(string $code_verifier): string {
        return $this->base64UrlEncode(hash('sha256', $code_verifier, true));
    }

    /**
     * Generates and stores a nonce in the session
     * 
     * @return string the nonce
     */
    private function setNonce(): string {
        $nonce = wp_create_nonce('scouting_oidc_nonce');
        $this->session->scouting_oidc_session_set('scouting_oidc_nonce', $nonce);
        return $nonce;
    }

    /**
     * Get stored nonce from the session
     *
     * @return string the nonce from the session
     */
    public function getNonce(): string {
        $nonce = $this->session->scouting_oidc_session_get('scouting_oidc_nonce');

        return is_string($nonce) ? $nonce : '';
    }

    /**
     * Adds a state to the stored array of states.
     * 
     * @param string $state the state to store
     * @return string the state
     */
    private function setState(string $state): string {
        // Retrieve the current array of states, or initialize as empty
        $states = $this->session->scouting_oidc_session_get('scouting_oidc_states') ?? [];

        // Ensure $states is an array (initialize as an empty array if it's null or not an array)
        if (!is_array($states)) {
            $states = [];
        }

        // Add the new state to the array
        $states[] = $state;

        // Store the updated array back in the session
        $this->session->scouting_oidc_session_set('scouting_oidc_states', $states);

        return $state;
    }

    /**
     * Stores the PKCE code_verifier in the session, keyed by state
     *
     * @param string $state the OIDC state value
     * @param string $code_verifier the generated code verifier
     * @return string the stored code verifier
     */
    private function setCodeVerifierForState(string $state, string $code_verifier): string {
        $verifiers = $this->session->scouting_oidc_session_get('scouting_oidc_code_verifiers') ?? [];
        if (!is_array($verifiers)) {
            $verifiers = [];
        }
        $verifiers[$state] = $code_verifier;
        $this->session->scouting_oidc_session_set('scouting_oidc_code_verifiers', $verifiers);

        return $code_verifier;
    }

    /**
     * Gets the stored PKCE code_verifier for a given state from the session
     *
     * @param string $state the OIDC state value
     * @return string|null
     */
    private function getCodeVerifierForState(string $state): ?string {
        $verifiers = $this->session->scouting_oidc_session_get('scouting_oidc_code_verifiers');
        if (!is_array($verifiers)) {
            return null;
        }

        $code_verifier = $verifiers[$state] ?? null;
        return is_string($code_verifier) ? $code_verifier : null;
    }

    /**
     * Check if a specific state exists in the stored array.
     *
     * @param string $state The state to search for
     * @return bool True if the state exists, false otherwise
     */
    public function hasState(string $state): bool {
        $states = $this->session->scouting_oidc_session_get('scouting_oidc_states') ?? [];

        // Ensure $states is an array
        if (!is_array($states)) {
            $states = [];
        }

        return in_array($state, $states, true);
    }

    /**
     * Encodes data as base64url without padding
     *
     * @param string $input
     * @return string
     */
    private function base64UrlEncode(string $input): string {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    /**
     * Decodes a base64url encoded string
     *
     * @param string $input
     * @return string|false the decoded string or false on failure
     */
    private function base64UrlDecode(string $input): string|false {
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
?>