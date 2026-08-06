<?php
namespace ScoutingOIDC;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checks the Scouts Online OpenID Connect provider for Site Health.
 */
class ProviderHealth {
    public const ISSUER = 'https://login.scouting.nl';

    /**
     * Register the authenticated REST endpoint used by the asynchronous test.
     *
     * @return void
     */
    public function registerRoute(): void {
        register_rest_route(
            'scouting-oidc/v1',
            '/site-health/provider',
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'restTest'],
                'permission_callback' => static fn() => current_user_can('view_site_health_checks'),
            ]
        );
    }

    /**
     * Return the provider result through the WordPress REST API.
     *
     * @return \WP_REST_Response
     */
    public function restTest(): \WP_REST_Response {
        $result = apply_filters('site_status_test_result', $this->test());

        return rest_ensure_response($result);
    }

    /**
     * Check discovery metadata, protocol capabilities, and signing keys.
     *
     * @return array
     */
    public function test(): array {
        $response = wp_remote_get(
            self::ISSUER . '/.well-known/openid-configuration',
            [
                'timeout' => 10,
                'redirection' => 3,
                'headers' => ['Accept' => 'application/json'],
            ]
        );

        if (is_wp_error($response)) {
            $result = $this->buildResult(
                __('The Scouts Online OpenID Connect provider could not be reached', 'scouting-openid-connect'),
                'critical',
                sprintf(__('Provider discovery failed: %s', 'scouting-openid-connect'), $response->get_error_message())
            );
        } elseif (wp_remote_retrieve_response_code($response) !== 200) {
            $result = $this->buildResult(
                __('The Scouts Online provider returned an unexpected response', 'scouting-openid-connect'),
                'critical',
                sprintf(
                    __('Provider discovery returned HTTP status %d instead of 200.', 'scouting-openid-connect'),
                    wp_remote_retrieve_response_code($response)
                )
            );
        } else {
            $metadata = json_decode(wp_remote_retrieve_body($response), true);
            $result = is_array($metadata)
                ? $this->evaluateMetadata($metadata)
                : $this->buildResult(
                    __('The Scouts Online provider returned invalid discovery data', 'scouting-openid-connect'),
                    'critical',
                    __('The provider response was not a valid JSON object.', 'scouting-openid-connect')
                );
        }

        return $result;
    }

    /**
     * Evaluate a decoded provider discovery document.
     *
     * @param array $metadata Provider discovery metadata.
     * @return array
     */
    private function evaluateMetadata(array $metadata): array {
        $result = $this->validateIssuer($metadata);

        if ($result === null) {
            $result = $this->validateEndpoints($metadata);
        }
        if ($result === null) {
            $result = $this->validateCapabilities($metadata);
        }
        if ($result === null) {
            $result = $this->validateScopes($metadata);
        }
        if ($result === null && !$this->hasValidLogoutEndpoint($metadata)) {
            $result = $this->buildResult(
                __('The provider does not advertise a logout endpoint', 'scouting-openid-connect'),
                'recommended',
                __('Login is available, but provider logout will fall back to the site home page.', 'scouting-openid-connect')
            );
        }
        if ($result === null) {
            $result = $this->checkSigningKeys($metadata['jwks_uri']);
        }

        return $result;
    }

    /**
     * Validate that discovery describes the configured issuer.
     *
     * @param array $metadata Provider discovery metadata.
     * @return array|null
     */
    private function validateIssuer(array $metadata): ?array {
        if (($metadata['issuer'] ?? null) === self::ISSUER) {
            return null;
        }

        return $this->buildResult(
            __('The provider discovery issuer does not match', 'scouting-openid-connect'),
            'critical',
            __('The discovered issuer does not match the issuer expected by Scouting OpenID Connect.', 'scouting-openid-connect')
        );
    }

    /**
     * Validate endpoints required by the client.
     *
     * @param array $metadata Provider discovery metadata.
     * @return array|null
     */
    private function validateEndpoints(array $metadata): ?array {
        $required_endpoints = [
            'authorization_endpoint',
            'token_endpoint',
            'userinfo_endpoint',
            'jwks_uri',
        ];
        $invalid_endpoints = array_values(
            array_filter(
                $required_endpoints,
                static fn($endpoint) => !isset($metadata[$endpoint])
                    || !is_string($metadata[$endpoint])
                    || filter_var($metadata[$endpoint], FILTER_VALIDATE_URL) === false
            )
        );

        if (empty($invalid_endpoints)) {
            return null;
        }

        return $this->buildResult(
            __('The provider discovery data is incomplete', 'scouting-openid-connect'),
            'critical',
            sprintf(
                __('These required endpoints are missing or invalid: %s.', 'scouting-openid-connect'),
                implode(', ', $invalid_endpoints)
            )
        );
    }

    /**
     * Validate protocol capabilities required by the client implementation.
     *
     * @param array $metadata Provider discovery metadata.
     * @return array|null
     */
    private function validateCapabilities(array $metadata): ?array {
        if (!in_array('S256', $this->metadataList($metadata, 'code_challenge_methods_supported'), true)) {
            return $this->buildResult(
                __('The provider does not advertise the required PKCE method', 'scouting-openid-connect'),
                'critical',
                __('Scouting OpenID Connect requires the S256 code challenge method.', 'scouting-openid-connect')
            );
        }

        $required_capabilities = [
            'response_types_supported' => ['code', 'response_type=code'],
            'grant_types_supported' => ['authorization_code', 'grant_type=authorization_code'],
            'id_token_signing_alg_values_supported' => ['RS256', 'id_token_signing_alg=RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'token_endpoint_auth_method=client_secret_post'],
        ];
        $missing_capabilities = [];

        foreach ($required_capabilities as $metadata_key => $capability) {
            if (!in_array($capability[0], $this->metadataList($metadata, $metadata_key), true)) {
                $missing_capabilities[] = $capability[1];
            }
        }

        if (empty($missing_capabilities)) {
            return null;
        }

        return $this->buildResult(
            __('The provider is missing required OpenID Connect capabilities', 'scouting-openid-connect'),
            'critical',
            sprintf(
                __('These capabilities are not advertised: %s.', 'scouting-openid-connect'),
                implode(', ', $missing_capabilities)
            )
        );
    }

    /**
     * Validate configured scopes against the provider discovery document.
     *
     * @param array $metadata Provider discovery metadata.
     * @return array|null
     */
    private function validateScopes(array $metadata): ?array {
        $supported_scopes = $this->metadataList($metadata, 'scopes_supported');
        $unsupported_scopes = empty($supported_scopes)
            ? []
            : array_values(array_diff($this->getConfiguredScopes(), $supported_scopes));

        if (empty($unsupported_scopes)) {
            return null;
        }

        return $this->buildResult(
            __('Configured scopes are not advertised by the provider', 'scouting-openid-connect'),
            'recommended',
            sprintf(__('Review these scopes: %s.', 'scouting-openid-connect'), implode(', ', $unsupported_scopes)),
            $this->settingsAction()
        );
    }

    /**
     * Check whether a valid logout endpoint is advertised.
     *
     * @param array $metadata Provider discovery metadata.
     * @return bool
     */
    private function hasValidLogoutEndpoint(array $metadata): bool {
        return isset($metadata['end_session_endpoint'])
            && is_string($metadata['end_session_endpoint'])
            && filter_var($metadata['end_session_endpoint'], FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check whether the discovered signing-key set is reachable and non-empty.
     *
     * @param string $jwks_uri Discovered JSON Web Key Set URL.
     * @return array
     */
    private function checkSigningKeys(string $jwks_uri): array {
        $response = wp_remote_get(
            $jwks_uri,
            [
                'timeout' => 10,
                'redirection' => 3,
                'headers' => ['Accept' => 'application/json'],
            ]
        );

        if (is_wp_error($response)) {
            $result = $this->buildResult(
                __('The provider signing keys could not be reached', 'scouting-openid-connect'),
                'critical',
                sprintf(__('Signing-key retrieval failed: %s', 'scouting-openid-connect'), $response->get_error_message())
            );
        } elseif (wp_remote_retrieve_response_code($response) !== 200) {
            $result = $this->buildResult(
                __('The provider signing keys returned an unexpected response', 'scouting-openid-connect'),
                'critical',
                sprintf(
                    __('Signing-key retrieval returned HTTP status %d instead of 200.', 'scouting-openid-connect'),
                    wp_remote_retrieve_response_code($response)
                )
            );
        } else {
            $jwks = json_decode(wp_remote_retrieve_body($response), true);
            $keys = is_array($jwks) && isset($jwks['keys']) && is_array($jwks['keys']) ? $jwks['keys'] : [];
            $result = $this->hasCompatibleSigningKey($keys)
                ? $this->buildResult(
                    __('The Scouts Online OpenID Connect provider is available', 'scouting-openid-connect'),
                    'good',
                    __('Discovery, required protocol capabilities, endpoints, and signing keys are available.', 'scouting-openid-connect')
                )
                : $this->buildResult(
                    __('The provider signing-key set is empty or invalid', 'scouting-openid-connect'),
                    'critical',
                    __('The provider did not return an RSA/RS256 signing key with the certificate chain required to validate ID tokens.', 'scouting-openid-connect')
                );
        }

        return $result;
    }

    /**
     * Return whether the key set contains a key usable by the token validator.
     *
     * @param array $keys JSON Web Keys.
     * @return bool
     */
    private function hasCompatibleSigningKey(array $keys): bool {
        foreach ($keys as $key) {
            if (!is_array($key)) {
                continue;
            }

            $certificate_chain = $key['x5c'] ?? [];
            if (
                ($key['kty'] ?? '') === 'RSA'
                && ($key['alg'] ?? '') === 'RS256'
                && is_string($key['kid'] ?? null)
                && ($key['kid'] ?? '') !== ''
                && is_array($certificate_chain)
                && is_string($certificate_chain[0] ?? null)
                && ($certificate_chain[0] ?? '') !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a list value from provider metadata.
     *
     * @param array $metadata Provider discovery metadata.
     * @param string $key Metadata key.
     * @return array
     */
    private function metadataList(array $metadata, string $key): array {
        $value = $metadata[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * Build a standard provider Site Health result.
     *
     * @param string $label Result title.
     * @param string $status Site Health status.
     * @param string $description Result details.
     * @param string $actions Optional action links.
     * @return array
     */
    private function buildResult(string $label, string $status, string $description, string $actions = ''): array {
        return [
            'label' => $label,
            'status' => $status,
            'badge' => [
                'label' => __('Scouting OpenID Connect', 'scouting-openid-connect'),
                'color' => 'blue',
            ],
            'description' => '<p>' . esc_html($description) . '</p>',
            'actions' => $actions,
            'test' => 'scouting_oidc_provider',
        ];
    }

    /**
     * Build a link to the plugin settings page.
     *
     * @return string
     */
    private function settingsAction(): string {
        return sprintf(
            '<p><a href="%1$s">%2$s</a></p>',
            esc_url(admin_url('admin.php?page=scouting-oidc-settings')),
            esc_html__('Review Scouting OpenID Connect settings', 'scouting-openid-connect')
        );
    }

    /**
     * Return configured scopes as a normalized list.
     *
     * @return array
     */
    private function getConfiguredScopes(): array {
        $scopes = preg_split('/\s+/', strtolower($this->getStringOption('scouting_oidc_scopes'))) ?: [];

        return array_values(array_unique(array_filter($scopes, static fn($scope) => $scope !== '')));
    }

    /**
     * Read a string option safely.
     *
     * @param string $option Option name.
     * @return string
     */
    private function getStringOption(string $option): string {
        $value = get_option($option, '');

        return is_string($value) ? trim($value) : '';
    }
}
