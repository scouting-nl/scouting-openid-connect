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

require_once plugin_dir_path( __FILE__ ) . 'class-providerhealth.php';

/**
 * Adds Scouting OpenID Connect checks and diagnostics to WordPress Site Health.
 *
 * @since 2.5.0
 */
class SiteHealth {
	/**
	 * Required OpenID Connect scopes.
	 *
	 * @since 2.5.0
	 *
	 * @var array<string>
	 */
	private const REQUIRED_SCOPES = array( 'openid', 'membership', 'profile', 'email' );

	/**
	 * Installed database schema version.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	private const LOGS_SCHEMA_VERSION = '1';

	/**
	 * Format used for Site Health debug timestamps.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	private const DEBUG_DATE_FORMAT = 'd-m-Y H:i:s T';

	/**
	 * The provider health check helper.
	 *
	 * @since 2.5.0
	 * @var ProviderHealth
	 */
	private ProviderHealth $provider_health;

	/**
	 * Whether the logs table is available.
	 *
	 * @since 2.5.0
	 * @var bool|null
	 */
	private ?bool $logs_table_available = null;

	/**
	 * Initializes Site Health checks.
	 *
	 * @since 2.5.0
	 *
	 * @param ProviderHealth $provider_health The provider health check helper.
	 */
	public function __construct( ProviderHealth $provider_health ) {
		$this->provider_health = $provider_health;
	}

	/**
	 * Registers direct Site Health tests.
	 *
	 * @since 2.5.0
	 *
	 * @param array $tests Registered Site Health tests.
	 * @return array Result data.
	 */
	public function site_health_tests( array $tests ): array {
		$tests['direct']['scouting_oidc_runtime']       = array(
			'label' => __( 'Scouting OpenID Connect runtime', 'scouting-openid-connect' ),
			'test'  => array( $this, 'runtime_test' ),
		);
		$tests['direct']['scouting_oidc_configuration'] = array(
			'label' => __( 'Scouting OpenID Connect configuration', 'scouting-openid-connect' ),
			'test'  => array( $this, 'configuration_test' ),
		);
		$tests['direct']['scouting_oidc_scopes']        = array(
			'label' => __( 'Scouting OpenID Connect scopes', 'scouting-openid-connect' ),
			'test'  => array( $this, 'scopes_test' ),
		);
		$tests['async']['scouting_oidc_provider']       = array(
			'label'             => __( 'Scouting OpenID Connect provider', 'scouting-openid-connect' ),
			'test'              => rest_url( 'scouting-oidc/v1/site-health/provider' ),
			'has_rest'          => true,
			'async_direct_test' => array( $this, 'provider_test' ),
		);
		$tests['direct']['scouting_oidc_redirect']      = array(
			'label' => __( 'Scouting OpenID Connect login redirect', 'scouting-openid-connect' ),
			'test'  => array( $this, 'redirect_test' ),
		);
		$tests['direct']['scouting_oidc_log_storage']   = array(
			'label' => __( 'Scouting OpenID Connect log storage', 'scouting-openid-connect' ),
			'test'  => array( $this, 'log_storage_test' ),
		);
		$tests['direct']['scouting_oidc_log_cleanup']   = array(
			'label' => __( 'Scouting OpenID Connect log cleanup', 'scouting-openid-connect' ),
			'test'  => array( $this, 'log_cleanup_test' ),
		);

		return $tests;
	}

	/**
	 * Checks local runtime requirements used directly by the login flow.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	public function runtime_test(): array {
		$required_functions = array( 'hash_equals', 'openssl_pkey_get_public', 'openssl_verify', 'random_bytes' );
		$missing_functions  = array_values(
			array_filter( $required_functions, static fn( $required_function ) => ! function_exists( $required_function ) )
		);

		if ( ! empty( $missing_functions ) ) {
			return $this->build_result(
				'scouting_oidc_runtime',
				__( 'The server is missing functions required for Scouts Online login', 'scouting-openid-connect' ),
				'critical',
				sprintf(
					/* translators: %s: Comma-separated list of missing PHP functions. */
					__( 'Ask the hosting provider to enable the PHP functions or extensions that provide: %s.', 'scouting-openid-connect' ),
					implode( ', ', $missing_functions )
				)
			);
		}

		if ( ! wp_is_using_https() ) {
			return $this->build_result(
				'scouting_oidc_runtime',
				__( 'Scouting OpenID Connect requires the site to use HTTPS', 'scouting-openid-connect' ),
				'critical',
				__( 'The login flow needs HTTPS to create its secure session cookie. Update both WordPress URLs to use HTTPS.', 'scouting-openid-connect' )
			);
		}

		return $this->build_result(
			'scouting_oidc_runtime',
			__( 'The server meets the Scouting OpenID Connect runtime requirements', 'scouting-openid-connect' ),
			'good',
			__( 'HTTPS and the PHP functions required for sessions and ID token validation are available.', 'scouting-openid-connect' )
		);
	}

	/**
	 * Checks whether the credentials needed to start a login are configured.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	public function configuration_test(): array {
		$missing = array();

		if ( $this->get_string_option( 'scouting_oidc_client_id' ) === '' ) {
			$missing[] = __( 'Client ID', 'scouting-openid-connect' );
		}

		if ( $this->get_string_option( 'scouting_oidc_client_secret' ) === '' ) {
			$missing[] = __( 'Client Secret', 'scouting-openid-connect' );
		}

		if ( ! empty( $missing ) ) {
			return $this->build_result(
				'scouting_oidc_configuration',
				__( 'Scouting OpenID Connect is not fully configured', 'scouting-openid-connect' ),
				'critical',
				sprintf(
					/* translators: %s: Comma-separated list of missing configuration values. */
					__( 'Add the following value(s) before users can log in with Scouts Online: %s.', 'scouting-openid-connect' ),
					implode( ', ', $missing )
				),
				$this->settings_action()
			);
		}

		return $this->build_result(
			'scouting_oidc_configuration',
			__( 'Scouting OpenID Connect credentials are configured', 'scouting-openid-connect' ),
			'good',
			__( 'A Client ID and Client Secret are stored. Site Health never displays the Client Secret.', 'scouting-openid-connect' )
		);
	}

	/**
	 * Checks required scopes and scopes needed by enabled profile features.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	public function scopes_test(): array {
		$scopes           = $this->get_configured_scopes();
		$missing_required = array_values( array_diff( self::REQUIRED_SCOPES, $scopes ) );

		if ( ! empty( $missing_required ) ) {
			return $this->build_result(
				'scouting_oidc_scopes',
				__( 'Required OpenID Connect scopes are missing', 'scouting-openid-connect' ),
				'critical',
				sprintf(
					/* translators: %s: Comma-separated list of missing OpenID Connect scopes. */
					__( 'Add these required scopes to the plugin and the connection in Scouts Online: %s.', 'scouting-openid-connect' ),
					implode( ', ', $missing_required )
				),
				$this->settings_action()
			);
		}

		$feature_mismatches = array();
		if ( get_option( 'scouting_oidc_user_phone' ) && ! in_array( 'phone', $scopes, true ) ) {
			$feature_mismatches[] = __( 'Phone storage is enabled without the phone scope.', 'scouting-openid-connect' );
		}
		if ( get_option( 'scouting_oidc_user_address' ) && ! in_array( 'address', $scopes, true ) ) {
			$feature_mismatches[] = __( 'Address storage is enabled without the address scope.', 'scouting-openid-connect' );
		}
		if ( get_option( 'scouting_oidc_user_woocommerce_sync' ) && ! class_exists( 'WooCommerce' ) ) {
			$feature_mismatches[] = __( 'WooCommerce synchronization is enabled while WooCommerce is not available.', 'scouting-openid-connect' );
		}

		if ( ! empty( $feature_mismatches ) ) {
			return $this->build_result(
				'scouting_oidc_scopes',
				__( 'Enabled profile features do not match the configured scopes', 'scouting-openid-connect' ),
				'recommended',
				implode( ' ', $feature_mismatches ),
				$this->settings_action()
			);
		}

		return $this->build_result(
			'scouting_oidc_scopes',
			__( 'OpenID Connect scopes match the enabled features', 'scouting-openid-connect' ),
			'good',
			sprintf(
				/* translators: %s: Space-separated list of configured OpenID Connect scopes. */
				__( 'All required scopes are configured. Current scopes: %s.', 'scouting-openid-connect' ),
				implode( ' ', $scopes )
			)
		);
	}

	/**
	 * Checks whether the configured post-login redirect can be used.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	public function redirect_test(): array {
		$mode        = $this->get_string_option( 'scouting_oidc_login_redirect' );
		$valid_modes = array( 'default', 'frontpage', 'dashboard', 'custom' );

		if ( ! in_array( $mode, $valid_modes, true ) ) {
			return $this->build_result(
				'scouting_oidc_redirect',
				__( 'The login redirect mode is invalid', 'scouting-openid-connect' ),
				'recommended',
				sprintf(
					/* translators: %s: Stored post-login redirect mode. */
					__( 'The stored redirect mode "%s" is not recognized.', 'scouting-openid-connect' ),
					$mode
				),
				$this->settings_action()
			);
		}

		$custom_redirect = $this->get_string_option( 'scouting_oidc_custom_redirect' );
		if ( 'custom' === $mode && ( '' === $custom_redirect || wp_validate_redirect( $custom_redirect, '' ) === '' ) ) {
			return $this->build_result(
				'scouting_oidc_redirect',
				__( 'The custom login redirect is not configured correctly', 'scouting-openid-connect' ),
				'recommended',
				__( 'Choose a valid page on this site or select another login redirect mode.', 'scouting-openid-connect' ),
				$this->settings_action()
			);
		}

		return $this->build_result(
			'scouting_oidc_redirect',
			__( 'The login redirect is configured', 'scouting-openid-connect' ),
			'good',
			sprintf(
				/* translators: %s: Current post-login redirect mode. */
				__( 'The current post-login redirect mode is %s.', 'scouting-openid-connect' ),
				$mode
			)
		);
	}

	/**
	 * Checks whether the database-backed log storage is present and current.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	public function log_storage_test(): array {
		if ( ! $this->logs_table_exists() ) {
			return $this->build_result(
				'scouting_oidc_log_storage',
				__( 'The Scouting OpenID Connect log table is missing', 'scouting-openid-connect' ),
				'recommended',
				__( 'Deactivate and reactivate the plugin to recreate its log storage.', 'scouting-openid-connect' )
			);
		}

		$expected_columns = array( 'id', 'user_id', 'sol_id', 'component', 'level', 'created_at', 'message' );
		$missing_columns  = array_values( array_diff( $expected_columns, $this->get_log_columns() ) );
		$installed_schema = $this->get_string_option( 'scouting_oidc_logs_schema_version' );
		$storage_issues   = array();

		if ( ! empty( $missing_columns ) ) {
			$storage_issues[] = sprintf(
				/* translators: %s: Comma-separated list of missing database column names. */
				__( 'Missing database columns: %s.', 'scouting-openid-connect' ),
				implode( ', ', $missing_columns )
			);
		}
		if ( self::LOGS_SCHEMA_VERSION !== $installed_schema ) {
			$storage_issues[] = sprintf(
				/* translators: 1: Installed log schema version. 2: Expected log schema version. */
				__( 'Installed schema version is %1$s; expected %2$s.', 'scouting-openid-connect' ),
				'' === $installed_schema ? __( 'not recorded', 'scouting-openid-connect' ) : $installed_schema,
				self::LOGS_SCHEMA_VERSION
			);
		}

		if ( ! empty( $storage_issues ) ) {
			return $this->build_result(
				'scouting_oidc_log_storage',
				__( 'The Scouting OpenID Connect log storage needs attention', 'scouting-openid-connect' ),
				'recommended',
				implode( ' ', $storage_issues )
			);
		}

		return $this->build_result(
			'scouting_oidc_log_storage',
			__( 'The Scouting OpenID Connect log storage is ready', 'scouting-openid-connect' ),
			'good',
			__( 'The log table exists with the expected schema and columns.', 'scouting-openid-connect' )
		);
	}

	/**
	 * Checks whether log retention cleanup is scheduled correctly.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	public function log_cleanup_test(): array {
		$next_cleanup   = wp_next_scheduled( CronJobs::CLEANUP_CRON_HOOK );
		$cleanup_action = '';

		if ( current_user_can( 'manage_options' ) ) {
			$cleanup_url    = wp_nonce_url(
				add_query_arg( 'action', CronJobs::RUN_CLEANUP_ACTION, admin_url( 'admin-post.php' ) ),
				CronJobs::RUN_CLEANUP_ACTION
			);
			$cleanup_action = sprintf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( $cleanup_url ),
				esc_html__( 'Run log cleanup now', 'scouting-openid-connect' )
			);
		}

		if ( false === $next_cleanup ) {
			return $this->build_result(
				'scouting_oidc_log_cleanup',
				__( 'Scouting OpenID Connect log cleanup is not scheduled', 'scouting-openid-connect' ),
				'recommended',
				__( 'The plugin could not find its daily cleanup event. Run cleanup now to recreate the schedule.', 'scouting-openid-connect' ),
				$cleanup_action
			);
		}

		$event           = wp_get_scheduled_event( CronJobs::CLEANUP_CRON_HOOK );
		$schedule_issues = array();
		if ( ! is_object( $event ) || 'daily' !== $event->schedule ) {
			$schedule_issues[] = __( 'The cleanup event is not using the daily schedule.', 'scouting-openid-connect' );
		}
		if ( $next_cleanup < time() - DAY_IN_SECONDS ) {
			$scheduled_time = wp_date( self::DEBUG_DATE_FORMAT, $next_cleanup );
			if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
				$schedule_issues[] = sprintf(
					/* translators: %s: Date and time of the overdue log cleanup event. */
					__( 'Automatic WP-Cron spawning is disabled, and the external cron runner has not processed the cleanup scheduled for %s.', 'scouting-openid-connect' ),
					$scheduled_time
				);
			} else {
				$schedule_issues[] = sprintf(
					/* translators: %s: Date and time of the overdue log cleanup event. */
					__( 'WordPress has not dispatched the cleanup scheduled for %s. Check the core scheduled-events and loopback-request Site Health tests.', 'scouting-openid-connect' ),
					$scheduled_time
				);
			}
		}

		if ( ! empty( $schedule_issues ) ) {
			return $this->build_result(
				'scouting_oidc_log_cleanup',
				__( 'Scouting OpenID Connect log cleanup needs attention', 'scouting-openid-connect' ),
				'recommended',
				implode( ' ', $schedule_issues ),
				$cleanup_action
			);
		}

		return $this->build_result(
			'scouting_oidc_log_cleanup',
			__( 'Scouting OpenID Connect log cleanup is scheduled', 'scouting-openid-connect' ),
			'good',
			sprintf(
				/* translators: 1: Date and time of the next log cleanup. 2: Number of days logs are retained. */
				__( 'The next cleanup is scheduled for %1$s and will retain %2$d days of logs.', 'scouting-openid-connect' ),
				wp_date( self::DEBUG_DATE_FORMAT, $next_cleanup ),
				CronJobs::scouting_oidc_get_log_retention_days()
			)
		);
	}

	/**
	 * Checks whether provider discovery is reachable and complete enough for login.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	public function provider_test(): array {
		return $this->provider_health->test();
	}

	/**
	 * Adds redacted plugin details to the Site Health information report.
	 *
	 * @since 2.5.0
	 *
	 * @param array $debug_information Registered debug information sections.
	 * @return array Result data.
	 */
	public function debug_information( array $debug_information ): array {
		$scopes           = $this->get_configured_scopes();
		$missing_required = array_values( array_diff( self::REQUIRED_SCOPES, $scopes ) );
		$profile_fields   = array();
		$log_statistics   = $this->get_log_statistics();

		foreach (
			array(
				'scouting_oidc_user_birthdate' => __( 'Birthdate', 'scouting-openid-connect' ),
				'scouting_oidc_user_gender'    => __( 'Gender', 'scouting-openid-connect' ),
				'scouting_oidc_user_phone'     => __( 'Phone', 'scouting-openid-connect' ),
				'scouting_oidc_user_address'   => __( 'Address', 'scouting-openid-connect' ),
			) as $option => $label
		) {
			if ( get_option( $option ) ) {
				$profile_fields[] = $label;
			}
		}

		$next_cleanup         = wp_next_scheduled( CronJobs::CLEANUP_CRON_HOOK );
		$next_cleanup_display = false === $next_cleanup
			? __( 'Not scheduled', 'scouting-openid-connect' )
			: wp_date( self::DEBUG_DATE_FORMAT, $next_cleanup );
		$last_cleanup         = CronJobs::scouting_oidc_get_last_cleanup_timestamp();
		$last_cleanup_display = null === $last_cleanup
			? __( 'Never recorded', 'scouting-openid-connect' )
			: wp_date( self::DEBUG_DATE_FORMAT, $last_cleanup );

		$debug_information['scouting-oidc'] = array(
			'label'       => __( 'Scouting OpenID Connect', 'scouting-openid-connect' ),
			'description' => __( 'Configuration and operational details. Client secrets and personal user claims are never included.', 'scouting-openid-connect' ),
			'show_count'  => true,
			'fields'      => array(
				'version'               => $this->debug_field( __( 'Plugin version', 'scouting-openid-connect' ), SCOUTING_OIDC_VERSION ),
				'issuer'                => $this->debug_field( __( 'Issuer', 'scouting-openid-connect' ), ProviderHealth::ISSUER ),
				'redirect_uri'          => $this->debug_field( __( 'Redirect URI', 'scouting-openid-connect' ), trailingslashit( home_url() ) ),
				'site_https'            => $this->debug_field( __( 'Site uses HTTPS', 'scouting-openid-connect' ), $this->enabled_text( wp_is_using_https() ) ),
				'openssl'               => $this->debug_field(
					__( 'PHP OpenSSL library', 'scouting-openid-connect' ),
					defined( 'OPENSSL_VERSION_TEXT' ) ? OPENSSL_VERSION_TEXT : __( 'Not available', 'scouting-openid-connect' )
				),
				'client_id'             => $this->debug_field(
					__( 'Client ID', 'scouting-openid-connect' ),
					$this->get_string_option( 'scouting_oidc_client_id' ) === ''
						? __( 'Not configured', 'scouting-openid-connect' )
						: __( 'Configured', 'scouting-openid-connect' )
				),
				'client_secret'         => $this->debug_field(
					__( 'Client Secret', 'scouting-openid-connect' ),
					$this->get_string_option( 'scouting_oidc_client_secret' ) === ''
						? __( 'Not configured', 'scouting-openid-connect' )
						: __( 'Configured', 'scouting-openid-connect' )
				),
				'scopes'                => $this->debug_field( __( 'Configured scopes', 'scouting-openid-connect' ), implode( ' ', $scopes ) ),
				'required_scopes'       => $this->debug_field(
					__( 'Required scopes', 'scouting-openid-connect' ),
					empty( $missing_required )
						? __( 'All present', 'scouting-openid-connect' )
						: sprintf(
							/* translators: %s: Comma-separated list of missing OpenID Connect scopes. */
							__( 'Missing: %s', 'scouting-openid-connect' ),
							implode( ', ', $missing_required )
						)
				),
				'profile_fields'        => $this->debug_field(
					__( 'Stored profile fields', 'scouting-openid-connect' ),
					empty( $profile_fields ) ? __( 'None', 'scouting-openid-connect' ) : implode( ', ', $profile_fields )
				),
				'auto_create'           => $this->debug_field( __( 'Automatic account creation', 'scouting-openid-connect' ), $this->enabled_text( (bool) get_option( 'scouting_oidc_user_auto_create' ) ) ),
				'duplicate_email'       => $this->debug_field( __( 'Duplicate email handling', 'scouting-openid-connect' ), $this->get_string_option( 'scouting_oidc_user_duplicate_email' ) ),
				'login_redirect'        => $this->debug_field( __( 'Login redirect mode', 'scouting-openid-connect' ), $this->get_string_option( 'scouting_oidc_login_redirect' ) ),
				'custom_redirect'       => $this->debug_field( __( 'Custom login redirect', 'scouting-openid-connect' ), $this->get_string_option( 'scouting_oidc_custom_redirect' ) ),
				'oidc_users'            => $this->debug_field( __( 'Scouting OpenID Connect users', 'scouting-openid-connect' ), (string) $this->get_oidc_user_count() ),
				'woocommerce_available' => $this->debug_field( __( 'WooCommerce available', 'scouting-openid-connect' ), $this->enabled_text( class_exists( 'WooCommerce' ) ) ),
				'woocommerce_sync'      => $this->debug_field( __( 'WooCommerce synchronization', 'scouting-openid-connect' ), $this->enabled_text( (bool) get_option( 'scouting_oidc_user_woocommerce_sync' ) ) ),
				'debug_logging'         => $this->debug_field( __( 'Debug logging', 'scouting-openid-connect' ), $this->enabled_text( (bool) get_option( 'scouting_oidc_debug_logging_enabled' ) ) ),
				'log_retention'         => $this->debug_field(
					__( 'Log retention', 'scouting-openid-connect' ),
					sprintf(
						/* translators: %d: Number of days logs are retained. */
						__( '%d days', 'scouting-openid-connect' ),
						CronJobs::scouting_oidc_get_log_retention_days()
					)
				),
				'logs_table'            => $this->debug_field( __( 'Log table', 'scouting-openid-connect' ), $this->logs_table_exists() ? __( 'Available', 'scouting-openid-connect' ) : __( 'Missing', 'scouting-openid-connect' ) ),
				'logs_schema'           => $this->debug_field( __( 'Log database schema', 'scouting-openid-connect' ), $this->get_string_option( 'scouting_oidc_logs_schema_version' ) ),
				'log_entries'           => $this->debug_field( __( 'Log entries', 'scouting-openid-connect' ), $log_statistics['total'] ),
				'recent_errors'         => $this->debug_field( __( 'Errors in the last 24 hours', 'scouting-openid-connect' ), $log_statistics['recent_errors'] ),
				'latest_log'            => $this->debug_field( __( 'Latest log entry', 'scouting-openid-connect' ), $log_statistics['latest'] ),
				'last_cleanup'          => $this->debug_field( __( 'Last successful log cleanup', 'scouting-openid-connect' ), $last_cleanup_display ),
				'next_cleanup'          => $this->debug_field( __( 'Next log cleanup', 'scouting-openid-connect' ), $next_cleanup_display ),
				'wp_cron'               => $this->debug_field(
					__( 'WordPress cron spawning', 'scouting-openid-connect' ),
					defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON
						? __( 'Disabled by configuration', 'scouting-openid-connect' )
						: __( 'Enabled', 'scouting-openid-connect' )
				),
				'discovery_cache'       => $this->debug_field(
					__( 'Provider discovery cache', 'scouting-openid-connect' ),
					get_transient( 'scouting_oidc_wk_' . md5( ProviderHealth::ISSUER ) ) === false
						? __( 'Empty', 'scouting-openid-connect' )
						: __( 'Populated', 'scouting-openid-connect' )
				),
				'jwks_cache'            => $this->debug_field(
					__( 'Provider signing-key cache', 'scouting-openid-connect' ),
					get_transient( 'scouting_oidc_jwks_' . md5( ProviderHealth::ISSUER ) ) === false
						? __( 'Empty', 'scouting-openid-connect' )
						: __( 'Populated', 'scouting-openid-connect' )
				),
			),
		);

		return $debug_information;
	}

	/**
	 * Builds a standard Site Health test result.
	 *
	 * @since 2.5.0
	 *
	 * @param string $test Test identifier.
	 * @param string $label Result title.
	 * @param string $status Site Health status.
	 * @param string $description Result details.
	 * @param string $actions Optional. Action links. Default empty string.
	 * @return array Result data.
	 */
	private function build_result( string $test, string $label, string $status, string $description, string $actions = '' ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Scouting OpenID Connect', 'scouting-openid-connect' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => $actions,
			'test'        => $test,
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
		$scope_option = strtolower( $this->get_string_option( 'scouting_oidc_scopes' ) );
		$scopes       = preg_split( '/\s+/', trim( $scope_option ) );
		$scopes       = $scopes ? $scopes : array();

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

	/**
	 * Returns whether the plugin log table exists.
	 *
	 * @since 2.5.0
	 *
	 * @return bool Whether the operation succeeds.
	 */
	private function logs_table_exists(): bool {
		global $wpdb;

		if ( null !== $this->logs_table_available ) {
			return $this->logs_table_available;
		}

		$table = $wpdb->prefix . 'scouting_oidc_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found_table = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
		);

		$this->logs_table_available = is_string( $found_table ) && $found_table === $table;

		return $this->logs_table_available;
	}

	/**
	 * Returns columns in the plugin log table.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	private function get_log_columns(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'scouting_oidc_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table ) );

		return is_array( $columns ) ? array_values( array_filter( $columns, 'is_string' ) ) : array();
	}

	/**
	 * Returns aggregate log information without exposing log messages or user data.
	 *
	 * @since 2.5.0
	 *
	 * @return array Result data.
	 */
	private function get_log_statistics(): array {
		global $wpdb;

		$unavailable = __( 'Not available', 'scouting-openid-connect' );
		$statistics  = array(
			'total'         => $unavailable,
			'recent_errors' => $unavailable,
			'latest'        => $unavailable,
		);

		if ( $this->logs_table_exists() ) {
			$table = $wpdb->prefix . 'scouting_oidc_logs';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					'SELECT COUNT(*) AS total, SUM(CASE WHEN created_at >= (UTC_TIMESTAMP(3) - INTERVAL 1 DAY) AND level IN (%s, %s, %s, %s) THEN 1 ELSE 0 END) AS recent_errors, MAX(created_at) AS latest FROM %i',
					LogLevel::EMERGENCY->value,
					LogLevel::ALERT->value,
					LogLevel::CRITICAL->value,
					LogLevel::ERROR->value,
					$table
				),
				ARRAY_A
			);

			if ( is_array( $row ) ) {
				$statistics['total']         = (string) ( (int) ( $row['total'] ?? 0 ) );
				$statistics['recent_errors'] = (string) ( (int) ( $row['recent_errors'] ?? 0 ) );
				$latest                      = $row['latest'] ?? null;
				$statistics['latest']        = is_string( $latest ) && '' !== $latest
					? Logger::scouting_oidc_format_utc_datetime_for_site( $latest, self::DEBUG_DATE_FORMAT )
					: __( 'None', 'scouting-openid-connect' );
			}
		}

		return $statistics;
	}

	/**
	 * Counts WordPress users managed by this plugin.
	 *
	 * @since 2.5.0
	 *
	 * @return int Integer value.
	 */
	private function get_oidc_user_count(): int {
		$users = new \WP_User_Query(
			array(
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Required to count plugin-managed users.
				'meta_key'    => 'scouting_oidc_user',
                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Required to select the ownership flag.
				'meta_value'  => 'true',
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		);

		return (int) $users->get_total();
	}

	/**
	 * Formats a boolean setting for diagnostics.
	 *
	 * @since 2.5.0
	 *
	 * @param bool $enabled Whether the setting is enabled.
	 * @return string String value.
	 */
	private function enabled_text( bool $enabled ): string {
		return $enabled
			? __( 'Enabled', 'scouting-openid-connect' )
			: __( 'Disabled', 'scouting-openid-connect' );
	}

	/**
	 * Builds a Site Health debug field.
	 *
	 * @since 2.5.0
	 *
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return array Result data.
	 */
	private function debug_field( string $label, string $value ): array {
		return array(
			'label' => $label,
			'value' => '' === $value ? __( 'Not available', 'scouting-openid-connect' ) : $value,
			'debug' => '' === $value ? 'not available' : $value,
		);
	}
}
