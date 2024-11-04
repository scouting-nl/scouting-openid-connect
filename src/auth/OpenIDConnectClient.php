<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use ScoutingOIDC\Session;

/**
 * OpenIDConnectClient for Scouting OpenID Connect
 *
 * @category   Scouting OpenID Connect
 * @package    OpenIDConnectClient
 * @author     Job van Koeveringe <job.van.koeveringe@scouting.nl>
 * @copyright  2024 Scouting Nederland
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
     * @var array holds well known data
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
        $this->redirectURL = $redirect_uri . '/';
        $this->issuer = $scouting_issuer;

        // Load session to store tokens if needed
        $this->session = new Session();
        $this->session->scouting_oidc_session_start();

        $this->getWellKnownData();
        $this->getJWKSData();
    }

    /**
     * Generates the authentication URL
     * 
     * @param string $response_type the response type
     * @param array $scopes_array an array of scopes
     * @return string|WP_Error returns the authentication URL or a WP_Error
     */
    public function getAuthenticationURL($response_type, $scopes_array) {
        // Check if authorization_endpoint is available in well known data
        if (!isset($this->wellKnownData->authorization_endpoint)) {
            return new WP_Error('oidc_error', esc_html__('The authorization endpoint is not available in the well known data.', 'scouting-openid-connect'), $this->wellKnownData);
        }

        // Set the scopes
        $this->setScopes($scopes_array);

        // Generate and store a nonce in the session
        // The nonce is an arbitrary value
        $nonce = $this->setNonce();

        // State essentially acts as a session key for OIDC
        $state = $this->setState($this->generateToken(32));

        $auth_params = [
            'client_id' => $this->clientID,
            'redirect_uri' => $this->redirectURL,
            'scope' => implode(' ', $this->scopes),
            'response_type' => $response_type,
            'nonce' => $nonce,
            'state' => $state,
        ];

        return $this->wellKnownData->authorization_endpoint . '?' . http_build_query($auth_params, '', '&', PHP_QUERY_RFC1738);
    }

    /**
     * Retrieves the tokens from the token endpoint
     * 
     * @param string $code the code from the authorization server
     * @return WP_Error returns a WP_Error if something went wrong
     */
    public function retrieveTokens($code) {
        // Check if token_endpoint is available in well known data
        if (!isset($this->wellKnownData->token_endpoint)) {
            return new WP_Error('oidc_error', esc_html__('The token endpoint is not available in the well known data.', 'scouting-openid-connect'), $this->wellKnownData);
        }

        // Set the grant type to authorization_code
        $grant_type = 'authorization_code';

        $data = array(
            'grant_type' => $grant_type,
            'client_id' => $this->clientID,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectURL,
            'code' => $code
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
            return $response;
        } 
        
        // Check if response code is 200 and response message is OK
        if (wp_remote_retrieve_response_code($response) !== 200 || wp_remote_retrieve_response_message($response) !== 'OK') {
            return new WP_Error('oidc_error', esc_html__('Failed to retrieve tokens. Response code:', 'scouting-openid-connect') . ' ' . esc_html(wp_remote_retrieve_response_code($response)) . ' ' . esc_html__('Response message:', 'scouting-openid-connect') . ' ' . esc_html(wp_remote_retrieve_response_message($response)), $response);
        }

        // Store the tokens
        $this->tokens = json_decode(wp_remote_retrieve_body($response));

        // Cleanup state and nonce
        $this->unsetStatesAndNonce();
    }

    /**
     * Function to unset the state and nonce
     */
    public function unsetStatesAndNonce() {
        $this->unsetStates();
        $this->unsetNonce();
    }

    /**
     * Validates the ID token and returns the payload
     * 
     * @return array|WP_Error returns the payload or a WP_Error if something went wrong
     */
    public function validateTokens() {
        // Check if id_token is available in tokens
        if (!isset($this->tokens->id_token)) {
            return new WP_Error('oidc_error', esc_html__('The ID token is not available in the tokens.', 'scouting-openid-connect'), $this->tokens);
        }

        // Check if jwks is available
        if (empty($this->jwks)) {
            return new WP_Error('oidc_error', esc_html__('The JSON Web Key Set (JWKS) is not available', 'scouting-openid-connect'), $this->jwks);
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
            return new WP_Error('oidc_error', esc_html__('The certificate chain (x5c) for the key ID (kid) specified in the header was not found.', 'scouting-openid-connect'), $this->jwks);
        }

        // Convert the certificate chain (x5c) to a public key certificate
        $public_key_certificate = "-----BEGIN CERTIFICATE-----\n" . chunk_split($x5c, 64, "\n") . "-----END CERTIFICATE-----";

        // Check if the signature is valid
        $publicKey = openssl_pkey_get_public($public_key_certificate);
        $signatureValid = openssl_verify($headerEncoded . '.' . $payloadEncoded, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($signatureValid !== 1) {
            return new WP_Error('oidc_error', esc_html__('The signature in the ID token is not valid.', 'scouting-openid-connect'), $this->tokens);
        }
        else {
            return $payload;
        }
    }

    /**
     * Gets the user info from the userinfo endpoint
     * 
     * @return object|WP_Error returns the user info or a WP_Error if something went wrong
     */
    public function getUserInfo() {
        // Check if userinfo_endpoint is available in well known data
        if (!isset($this->wellKnownData->userinfo_endpoint)) {
            return new WP_Error('oidc_error', esc_html__('The userinfo endpoint is not available in the well known data.', 'scouting-openid-connect'), $this->wellKnownData);
        }

        // Check if access_token is available in tokens
        if (!isset($this->tokens->access_token)) {
            return new WP_Error('oidc_error', esc_html__('The access token is not available in the tokens.', 'scouting-openid-connect'), $this->tokens);
        }

        // Set the arguments for the GET request
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->tokens->access_token
           )
       );

        $response = wp_remote_get($this->wellKnownData->userinfo_endpoint, $args);
        if (is_wp_error($response)) {
            return $response;
        } 
        
        // Check if response code is 200 and response message is OK
        if (wp_remote_retrieve_response_code($response) !== 200 || wp_remote_retrieve_response_message($response) !== 'OK') {
            return new WP_Error('oidc_error', esc_html__('Failed to retrieve user info. Response code:', 'scouting-openid-connect') . ' ' . esc_html(wp_remote_retrieve_response_code($response)) . ' ' . esc_html__('Response message:', 'scouting-openid-connect') . ' ' . esc_html(wp_remote_retrieve_response_message($response)), $response);
        }

        return json_decode(wp_remote_retrieve_body($response));
    }

    /**
     * Gets the logout URL
     * 
     * @return string|WP_Error returns the logout URL or a WP_Error if something went wrong
     */
    public function getLogoutUrl() {
        // Check if end_session_endpoint is available in well known data
        if (!isset($this->wellKnownData->end_session_endpoint)) {
            return new WP_Error('oidc_error', esc_html__('The end session endpoint is not available in the well known data.', 'scouting-openid-connect'), $this->wellKnownData);
        }

        // add id_token_hint & client_id to the logout URL
        $logout_params = [
            'id_token_hint' => $this->tokens->id_token,
            'client_id' => $this->clientID,
        ];
        
        return $this->wellKnownData->end_session_endpoint . '?' . http_build_query($logout_params, '', '&', PHP_QUERY_RFC1738);
    }

    /**
     * Gets anything that we need configuration wise including endpoints, and other values
     *
     * @return WP_Error|void returns a WP_Error if something went wrong
     */
    private function getWellKnownData() {
        // Check if $this->wellKnownData has already been set
        if (!empty($this->wellKnownData)) {
            return;
        }

        // Check if $this->issuer is not empty
        if (empty($this->issuer)) {
            return new WP_Error('oidc_error', esc_html__('An issuer must be provided in the config.', 'scouting-openid-connect'), $this->issuer);
        }

        // Check if $this->issuer is not a valid URL
        if (!filter_var($this->issuer, FILTER_VALIDATE_URL)) {
            return new WP_Error('oidc_error', esc_html__('The issuer URL is not valid.', 'scouting-openid-connect'), $this->issuer);
        }

        $well_known_config_url = $this->issuer . '/.well-known/openid-configuration';

        // Get the well known configuration from the issuer
        $response = wp_remote_get($well_known_config_url);
        if (is_wp_error($response)) {
            return $response;
        } else {
            $this->wellKnownData = json_decode(wp_remote_retrieve_body($response));
        }
    }

    /**
     * Sets the scopes
     * 
     * @param array $scopes_array an array of scopes
     * @return WP_Error|void returns a WP_Error if something went wrong
     */
    private function setScopes(array $scopes_array) {
        // Check if $scopes_array is not a valid array or is empty
        if (!is_array($scopes_array) || empty($scopes_array)) {
            return new WP_Error('oidc_error', esc_html__('Scopes must be a non-empty array.', 'scouting-openid-connect'), $scopes_array);
        }

        // Check if scopes are allowed by the server
        if (isset($this->wellKnownData->scopes_supported)) {
            foreach ($scopes_array as $scope) {
                if (!in_array($scope, $this->wellKnownData->scopes_supported)) {
                    return new WP_Error('oidc_error', esc_html__('Scope', 'scouting-openid-connect') . ' '. esc_html($scope) . ' ' . esc_html__('is not supported by the server, supported scopes are:', 'scouting-openid-connect') . ' ' . implode(', ', array_map('esc_html', $this->wellKnownData->scopes_supported)), [$scope, $this->wellKnownData->scopes_supported]);
                }
            }
        }

        // Set the scopes
        $this->scopes = $scopes_array;
    }

    /**
     * Gets the JSON Web Key Set (JWKS) from the jwks_uri
     *
     * @return WP_Error|void returns a WP_Error if something went wrong
     */
    private function getJWKSData() {
        // Check if well known data is available
        if (!$this->wellKnownData) {
            return new WP_Error('oidc_error', esc_html__('Well known data is not available.', 'scouting-openid-connect'), $this->wellKnownData);
        }

        // Check if jwks_uri is available in well known data
        if (!isset($this->wellKnownData->jwks_uri)) {
            return new WP_Error('oidc_error', esc_html__('The jwks_uri is not available in the well known data.', 'scouting-openid-connect'), $this->wellKnownData);
        }

        // Check if jwks_uri is a valid URL
        if (!filter_var($this->wellKnownData->jwks_uri, FILTER_VALIDATE_URL)) {
            return new WP_Error('oidc_error', esc_html__('The jwks_uri is not a valid URL.', 'scouting-openid-connect'), $this->wellKnownData->jwks_uri);
        }

        // Get the JSON Web Key Set (JWKS) from the jwks_uri
        $response = wp_remote_get($this->wellKnownData->jwks_uri);
        if (is_wp_error($response)) {
            return $response;
        } else {
            $this->jwks = json_decode(wp_remote_retrieve_body($response));
        }
    }

    /**
     * Generates a random token
     *
     * @param int $length the length of the token
     * @return string the token
     */
    private function generateToken($length) {
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
     * Stores nonce
     * 
     * @return string the nonce
     */
    private function setNonce() {
        $nonce = wp_create_nonce('scouting_oidc_nonce');
        $this->session->scouting_oidc_session_set('scouting_oidc_nonce', $nonce);
        return $nonce;
    }

    /**
     * Get stored nonce
     *
     * @return string|null
     */
    public function getNonce() {
        return $this->session->scouting_oidc_session_get('scouting_oidc_nonce');
    }

    /**
     * Cleanup nonce
     *
     * @return void
     */
    private function unsetNonce() {
        $this->session->scouting_oidc_session_delete('scouting_oidc_nonce');
    }

    /**
     * Adds a state to the stored array of states.
     * 
     * @param string $state the state to store
     * @return string the state
     */
    private function setState(string $state) {
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
     * Check if a specific state exists in the stored array.
     *
     * @param string $state The state to search for
     * @return bool True if the state exists, false otherwise
     */
    public function hasState(string $state): bool {
        $states = $this->session->scouting_oidc_session_get('scouting_oidc_states') ?? [];
        return in_array($state, $states, true);
    }

    /**
     * Cleanup state from session
     * 
     * @return void
     */
    private function unsetStates() {
        $this->session->scouting_oidc_session_delete('scouting_oidc_states');
    }

    /**
     * Decodes a base64url encoded string
     *
     * @param string $input
     * @return string the decoded string
     */
    private function base64UrlDecode($input) {
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
?>