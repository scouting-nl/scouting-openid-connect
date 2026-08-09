<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.5.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks the Scouts Online OpenID Connect provider for Site Health.
 *
 * @since 2.5.0
 */
class ProviderHealth {
	/**
	 * Default OpenID Connect issuer URL.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	public const ISSUER = 'https://login.scouting.nl';

	/**
	 * Registers the authenticated REST endpoint used by the asynchronous test.
	 *
	 * @since 2.5.0
	 */
	public function register_route(): void {
		register_rest_route(
			'scouting-oidc/v1',
			'/site-health/provider',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_test' ),
				'permission_callback' => static fn() => current_user_can( 'view_site_health_checks' ),
			)
		);
	}

	/**
	 * Returns the provider result through the WordPress REST API.
	 *
	 * @since 2.5.0
	 *
	 * @return \WP_REST_Response REST response.
	 */
	public function rest_test(): \WP_REST_Response {
		/**
		 * Filters the Site Health test result.
		 *
		 * @since 2.5.0
		 *
		 * @param array $result Site Health test result.
		 */
		$result = apply_filters( 'site_status_test_result', $this->test() );

		return rest_ensure_response( $result );
	}

	/**
	 * Checks discovery metadata, protocol capabilities, and signing keys.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	public function test(): array {
		$response = wp_remote_get(
			self::ISSUER . '/.well-known/openid-configuration',
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$result = $this->build_result(
				__( 'The Scouts Online OpenID Connect provider could not be reached', 'scouting-openid-connect' ),
				'critical',
				sprintf(
					/* translators: %s: Error message returned while requesting provider discovery. */
					__( 'Provider discovery failed: %s', 'scouting-openid-connect' ),
					$response->get_error_message()
				)
			);
		} elseif ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$result = $this->build_result(
				__( 'The Scouts Online provider returned an unexpected response', 'scouting-openid-connect' ),
				'critical',
				sprintf(
					/* translators: %d: HTTP status code returned by provider discovery. */
					__( 'Provider discovery returned HTTP status %d instead of 200.', 'scouting-openid-connect' ),
					wp_remote_retrieve_response_code( $response )
				)
			);
		} else {
			$metadata = json_decode( wp_remote_retrieve_body( $response ), true );
			$result   = is_array( $metadata )
				? $this->evaluate_metadata( $metadata )
				: $this->build_result(
					__( 'The Scouts Online provider returned invalid discovery data', 'scouting-openid-connect' ),
					'critical',
					__( 'The provider response was not a valid JSON object.', 'scouting-openid-connect' )
				);
		}

		return $result;
	}

	/**
	 * Evaluates a decoded provider discovery document.
	 *
	 * @since 2.5.0
	 *
	 * @param array $metadata Provider discovery metadata.
	 * @return array Result data.
	 */
	private function evaluate_metadata( array $metadata ): array {
		$result = $this->validate_issuer( $metadata );

		if ( null === $result ) {
			$result = $this->validate_endpoints( $metadata );
		}
		if ( null === $result ) {
			$result = $this->validate_capabilities( $metadata );
		}
		if ( null === $result ) {
			$result = $this->validate_scopes( $metadata );
		}
		if ( null === $result && ! $this->has_valid_logout_endpoint( $metadata ) ) {
			$result = $this->build_result(
				__( 'The provider does not advertise a logout endpoint', 'scouting-openid-connect' ),
				'recommended',
				__( 'Login is available, but provider logout will fall back to the site home page.', 'scouting-openid-connect' )
			);
		}
		if ( null === $result ) {
			$result = $this->check_signing_keys( $metadata['jwks_uri'] );
		}

		return $result;
	}

	/**
	 * Validates that discovery describes the configured issuer.
	 *
	 * @since 2.5.0
	 *
	 * @param array $metadata Provider discovery metadata.
	 * @return array|null Result data or null.
	 */
	private function validate_issuer( array $metadata ): ?array {
		if ( ( $metadata['issuer'] ?? null ) === self::ISSUER ) {
			return null;
		}

		return $this->build_result(
			__( 'The provider discovery issuer does not match', 'scouting-openid-connect' ),
			'critical',
			__( 'The discovered issuer does not match the issuer expected by Scouting OpenID Connect.', 'scouting-openid-connect' )
		);
	}

	/**
	 * Validates endpoints required by the client.
	 *
	 * @since 2.5.0
	 *
	 * @param array $metadata Provider discovery metadata.
	 * @return array|null Result data or null.
	 */
	private function validate_endpoints( array $metadata ): ?array {
		$required_endpoints = array(
			'authorization_endpoint',
			'token_endpoint',
			'userinfo_endpoint',
			'jwks_uri',
		);
		$invalid_endpoints  = array_values(
			array_filter(
				$required_endpoints,
				static fn( $endpoint ) => ! isset( $metadata[ $endpoint ] )
					|| ! is_string( $metadata[ $endpoint ] )
					|| filter_var( $metadata[ $endpoint ], FILTER_VALIDATE_URL ) === false
			)
		);

		if ( empty( $invalid_endpoints ) ) {
			return null;
		}

		return $this->build_result(
			__( 'The provider discovery data is incomplete', 'scouting-openid-connect' ),
			'critical',
			sprintf(
				/* translators: %s: Comma-separated list of missing or invalid provider endpoints. */
				__( 'These required endpoints are missing or invalid: %s.', 'scouting-openid-connect' ),
				implode( ', ', $invalid_endpoints )
			)
		);
	}

	/**
	 * Validates protocol capabilities required by the client implementation.
	 *
	 * @since 2.5.0
	 *
	 * @param array $metadata Provider discovery metadata.
	 * @return array|null Result data or null.
	 */
	private function validate_capabilities( array $metadata ): ?array {
		if ( ! in_array( 'S256', $this->metadata_list( $metadata, 'code_challenge_methods_supported' ), true ) ) {
			return $this->build_result(
				__( 'The provider does not advertise the required PKCE method', 'scouting-openid-connect' ),
				'critical',
				__( 'Scouting OpenID Connect requires the S256 code challenge method.', 'scouting-openid-connect' )
			);
		}

		$required_capabilities = array(
			'response_types_supported'              => array( 'code', 'response_type=code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'grant_type=authorization_code' ),
			'id_token_signing_alg_values_supported' => array( 'RS256', 'id_token_signing_alg=RS256' ),
			'token_endpoint_auth_methods_supported' => array( 'client_secret_post', 'token_endpoint_auth_method=client_secret_post' ),
		);
		$missing_capabilities  = array();

		foreach ( $required_capabilities as $metadata_key => $capability ) {
			if ( ! in_array( $capability[0], $this->metadata_list( $metadata, $metadata_key ), true ) ) {
				$missing_capabilities[] = $capability[1];
			}
		}

		if ( empty( $missing_capabilities ) ) {
			return null;
		}

		return $this->build_result(
			__( 'The provider is missing required OpenID Connect capabilities', 'scouting-openid-connect' ),
			'critical',
			sprintf(
				/* translators: %s: Comma-separated list of missing OpenID Connect capabilities. */
				__( 'These capabilities are not advertised: %s.', 'scouting-openid-connect' ),
				implode( ', ', $missing_capabilities )
			)
		);
	}

	/**
	 * Validates configured scopes against the provider discovery document.
	 *
	 * @since 2.5.0
	 *
	 * @param array $metadata Provider discovery metadata.
	 * @return array|null Result data or null.
	 */
	private function validate_scopes( array $metadata ): ?array {
		$supported_scopes   = $this->metadata_list( $metadata, 'scopes_supported' );
		$unsupported_scopes = empty( $supported_scopes )
			? array()
			: array_values( array_diff( $this->get_configured_scopes(), $supported_scopes ) );

		if ( empty( $unsupported_scopes ) ) {
			return null;
		}

		return $this->build_result(
			__( 'Configured scopes are not advertised by the provider', 'scouting-openid-connect' ),
			'recommended',
			sprintf(
				/* translators: %s: Comma-separated list of scopes not advertised by the provider. */
				__( 'Review these scopes: %s.', 'scouting-openid-connect' ),
				implode( ', ', $unsupported_scopes )
			),
			$this->settings_action()
		);
	}

	/**
	 * Checks whether a valid logout endpoint is advertised.
	 *
	 * @since 2.5.0
	 *
	 * @param array $metadata Provider discovery metadata.
	 * @return bool Whether the operation succeeds.
	 */
	private function has_valid_logout_endpoint( array $metadata ): bool {
		return isset( $metadata['end_session_endpoint'] )
			&& is_string( $metadata['end_session_endpoint'] )
			&& filter_var( $metadata['end_session_endpoint'], FILTER_VALIDATE_URL ) !== false;
	}

	/**
	 * Checks whether the discovered signing-key set is reachable and non-empty.
	 *
	 * @since 2.5.0
	 *
	 * @param string $jwks_uri Discovered JSON Web Key Set URL.
	 * @return array Result data.
	 */
	private function check_signing_keys( string $jwks_uri ): array {
		$response = wp_remote_get(
			$jwks_uri,
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$result = $this->build_result(
				__( 'The provider signing keys could not be reached', 'scouting-openid-connect' ),
				'critical',
				sprintf(
					/* translators: %s: Error message returned while requesting provider signing keys. */
					__( 'Signing-key retrieval failed: %s', 'scouting-openid-connect' ),
					$response->get_error_message()
				)
			);
		} elseif ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$result = $this->build_result(
				__( 'The provider signing keys returned an unexpected response', 'scouting-openid-connect' ),
				'critical',
				sprintf(
					/* translators: %d: HTTP status code returned by the signing-key endpoint. */
					__( 'Signing-key retrieval returned HTTP status %d instead of 200.', 'scouting-openid-connect' ),
					wp_remote_retrieve_response_code( $response )
				)
			);
		} else {
			$jwks   = json_decode( wp_remote_retrieve_body( $response ), true );
			$keys   = is_array( $jwks ) && isset( $jwks['keys'] ) && is_array( $jwks['keys'] ) ? $jwks['keys'] : array();
			$result = $this->has_compatible_signing_key( $keys )
				? $this->build_result(
					__( 'The Scouts Online OpenID Connect provider is available', 'scouting-openid-connect' ),
					'good',
					__( 'Discovery, required protocol capabilities, endpoints, and signing keys are available.', 'scouting-openid-connect' )
				)
				: $this->build_result(
					__( 'The provider signing-key set is empty or invalid', 'scouting-openid-connect' ),
					'critical',
					__( 'The provider did not return an RSA/RS256 signing key with the certificate chain required to validate ID tokens.', 'scouting-openid-connect' )
				);
		}

		return $result;
	}

	/**
	 * Returns whether the key set contains a key usable by the token validator.
	 *
	 * @since 2.5.0
	 *
	 * @param array $keys JSON Web Keys.
	 * @return bool Whether the operation succeeds.
	 */
	private function has_compatible_signing_key( array $keys ): bool {
		foreach ( $keys as $key ) {
			if ( ! is_array( $key ) ) {
				continue;
			}

			$certificate_chain = $key['x5c'] ?? array();
			if (
				( $key['kty'] ?? '' ) === 'RSA'
				&& ( $key['alg'] ?? '' ) === 'RS256'
				&& is_string( $key['kid'] ?? null )
				&& ( $key['kid'] ?? '' ) !== ''
				&& is_array( $certificate_chain )
				&& is_string( $certificate_chain[0] ?? null )
				&& ( $certificate_chain[0] ?? '' ) !== ''
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns a list value from provider metadata.
	 *
	 * @since 2.5.0
	 *
	 * @param array  $metadata Provider discovery metadata.
	 * @param string $key Metadata key.
	 * @return array Result data.
	 */
	private function metadata_list( array $metadata, string $key ): array {
		$value = $metadata[ $key ] ?? array();

		return is_array( $value ) ? $value : array();
	}

	/**
	 * Builds a standard provider Site Health result.
	 *
	 * @since 2.5.0
	 *
	 * @param string $label Result title.
	 * @param string $status Site Health status.
	 * @param string $description Result details.
	 * @param string $actions Optional. Action links. Default empty string.
	 * @return array Result data.
	 */
	private function build_result( string $label, string $status, string $description, string $actions = '' ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Scouting OpenID Connect', 'scouting-openid-connect' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => $actions,
			'test'        => 'scouting_oidc_provider',
		);
	}

	/**
	 * Builds a link to the plugin settings page.
	 *
	 * @since 2.5.0
	 *
	 * @return string String value.
	 */
	private function settings_action(): string {
		return sprintf(
			'<p><a href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'admin.php?page=scouting-oidc-settings' ) ),
			esc_html__( 'Review Scouting OpenID Connect settings', 'scouting-openid-connect' )
		);
	}

	/**
	 * Returns configured scopes as a normalized list.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	private function get_configured_scopes(): array {
		$scopes = preg_split( '/\s+/', strtolower( $this->get_string_option( 'scouting_oidc_scopes' ) ) );
		$scopes = $scopes ? $scopes : array();

		return array_values( array_unique( array_filter( $scopes, static fn( $scope ) => '' !== $scope ) ) );
	}

	/**
	 * Reads a string option safely.
	 *
	 * @since 2.5.0
	 *
	 * @param string $option Option name.
	 * @return string String value.
	 */
	private function get_string_option( string $option ): string {
		$value = get_option( $option, '' );

		return is_string( $value ) ? trim( $value ) : '';
	}
}
