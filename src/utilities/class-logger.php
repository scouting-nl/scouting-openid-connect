<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.4.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;

require_once __DIR__ . '/class-logcomponent.php';
require_once __DIR__ . '/class-loglevel.php';

/**
 * Provides database-backed logging for Scouting OIDC.
 *
 * Provides convenience wrappers for logging at various severity levels and
 * a small installer to create the underlying logs table.
 *
 * @since 2.4.0
 */
class Logger {
	/**
	 * Current schema version for the logs table.
	 *
	 * @since 2.4.0
	 */
	private const LOGS_SCHEMA_VERSION = '1';

	/**
	 * Option key used to persist installed logs schema version.
	 *
	 * @since 2.4.0
	 */
	private const LOGS_SCHEMA_VERSION_OPTION = 'scouting_oidc_logs_schema_version';

	/**
	 * Ensures the logs table exists and is up to date for plugin updates.
	 *
	 * @since 2.4.0
	 */
	public function scouting_oidc_logger_maybe_upgrade_database(): void {
		global $wpdb;

		$logs_table        = $wpdb->prefix . 'scouting_oidc_logs';
		$installed_version = get_option( self::LOGS_SCHEMA_VERSION_OPTION, '' );

		if ( self::LOGS_SCHEMA_VERSION === $installed_version && $this->scouting_oidc_logger_table_exists( $logs_table ) ) {
			return;
		}

		$this->scouting_oidc_logger_database_create();
	}

	/**
	 * Creates or updates the logs table during plugin activation.
	 *
	 * @since 2.4.0
	 */
	public function scouting_oidc_logger_database_create(): void {
		global $wpdb;

		$logs_table      = $wpdb->prefix . 'scouting_oidc_logs';
		$charset_collate = $wpdb->get_charset_collate();

		// Build SQL ENUM values from the LogComponent enum cases.
		$enum_component_values = "'" . implode(
			"','",
			array_map(
				fn( $enum_case ) => $enum_case->value,
				LogComponent::cases()
			)
		) . "'";

		// Build SQL ENUM values from the LogLevel enum cases.
		$enum_level_values = "'" . implode(
			"','",
			array_map(
				fn( $enum_case ) => $enum_case->value,
				LogLevel::cases()
			)
		) . "'";

		// Create the logs table with appropriate columns and basic indexes.
		$sql = "CREATE TABLE {$logs_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL,
            sol_id VARCHAR(60) NULL,
            component ENUM($enum_component_values) NOT NULL,
            level ENUM($enum_level_values) NOT NULL,
            created_at DATETIME(3) NOT NULL DEFAULT UTC_TIMESTAMP(3),
            message TEXT NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY sol_id (sol_id),
            KEY component (component),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Ensure the table engine supports foreign keys (InnoDB).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ENGINE=InnoDB', $logs_table ) );

		// Only add FK if it doesn't already exist.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_fk = $wpdb->get_var(
			$wpdb->prepare(
				'
                SELECT CONSTRAINT_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = %s
                AND COLUMN_NAME = %s
                AND REFERENCED_TABLE_NAME = %s
                ',
				$logs_table,
				'user_id',
				$wpdb->users
			)
		);

		if ( ! $existing_fk ) {
			// Add a foreign key constraint on user_id referencing the WP users table, with cascading deletes to maintain referential integrity. This ensures that if a user is deleted from WordPress, all their associated log entries will also be removed, preventing orphaned log records.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD CONSTRAINT %i FOREIGN KEY (user_id) REFERENCES %i(ID) ON DELETE CASCADE', $logs_table, 'fk_scouting_logs_user', $wpdb->users ) );
		}

		update_option( self::LOGS_SCHEMA_VERSION_OPTION, self::LOGS_SCHEMA_VERSION );
	}

	/**
	 * Persists a log entry to the database.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param LogLevel     $level Severity level for this log entry.
	 * @param string       $message Log message content.
	 * @param int|null     $user_id Optional. WP user ID to associate with this entry. Default null.
	 * @param string|null  $sol_id Optional. SOL identifier to associate with this entry. Default null.
	 */
	private static function log( LogComponent $component, LogLevel $level, string $message, ?int $user_id = null, ?string $sol_id = null ): void {
		global $wpdb;

		if ( LogLevel::DEBUG === $level && ! self::is_debug_logging_enabled() ) {
			return;
		}

		// If $user_id is not provided, attempt to use the current user's ID if available.
		if ( null === $user_id ) {
			$user_id = get_current_user_id();

			// `get_current_user_id()` returns 0 when no user is available; convert 0 to null
			if ( 0 === $user_id ) {
				$user_id = null;
			}
		} else {
			// If a $user_id is provided, verify that it corresponds to an existing user.
			$user_exists = get_user_by( 'ID', $user_id );
			if ( false === $user_exists ) {
				// If the user ID does not correspond to a real user, clear it so
				// we don't store an invalid user_id in the logs.
				$user_id = null;
			}
		}

		// If $sol_id is not provided and we have a valid $user_id, attempt to use the user's login as the SOL ID.
		if ( empty( $sol_id ) && null !== $user_id ) {
			$user = get_userdata( $user_id );

			// Check $user is valid and has a user_login before using it as sol_id.
			if ( false !== $user && ! empty( $user->user_login ) ) {
				$sol_id = $user->user_login;
			} else {
				// If we can't derive a valid sol_id, set it to null to avoid storing empty strings.
				$sol_id = null;
			}
		} elseif ( is_string( $sol_id ) && '' === trim( $sol_id ) ) {
			// If a $sol_id is provided, ensure it's a non-empty string. If it's empty, set it to null.
			$sol_id = null;
		}

		$created_at = ( new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s.v' );

		self::maybe_log_to_wp_debug( $component, $level, $message, $user_id, $sol_id );

		// Insert the log entry. Format specifiers ensure proper data typing.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'scouting_oidc_logs',
			array(
				'component'  => $component->value,
				'level'      => $level->value,
				'message'    => $message,
				'user_id'    => $user_id,
				'sol_id'     => $sol_id,
				'created_at' => $created_at,
			),
			array(
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Formats a UTC datetime string for the current site timezone.
	 *
	 * @since 2.4.0
	 *
	 * @param string $datetime UTC datetime in MySQL DATETIME(3) format.
	 * @param string $format Optional. Output format. Default 'd-m-Y H:i:s.v'.
	 * @return string String value.
	 */
	public static function scouting_oidc_format_utc_datetime_for_site( string $datetime, string $format = 'd-m-Y H:i:s.v' ): string {
		$datetime = trim( $datetime );
		if ( '' === $datetime ) {
			return '';
		}

		$utc_timezone  = new \DateTimeZone( 'UTC' );
		$site_timezone = wp_timezone();

		$parsed_datetime = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s.v', $datetime, $utc_timezone );
		if ( false === $parsed_datetime ) {
			$parsed_datetime = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $datetime, $utc_timezone );
			if ( false === $parsed_datetime ) {
				return $datetime;
			}
		}

		return $parsed_datetime->setTimezone( $site_timezone )->format( $format );
	}

	/**
	 * Logs a WP_Error object at the error level, including all error codes and messages in the log entry.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param LogLevel     $level Severity level for this log entry.
	 * @param WP_Error     $wp_error The WP_Error object to log.
	 * @param int|null     $user_id Optional. WP user ID to associate with this error. Default null.
	 * @param string|null  $sol_id Optional. SOL identifier to associate with this error. Default null.
	 */
	public static function log_wp_error( LogComponent $component, LogLevel $level, WP_Error $wp_error, ?int $user_id = null, ?string $sol_id = null ): void {
		$codes = $wp_error->get_error_codes();

		// Normalize to a codes array so we have a single processing path.
		if ( empty( $codes ) ) {
			$codes = array( 'generic' );
		}

		// Build log lines for each error code, including the generic message if no specific codes are present.
		$lines = array_map(
			function ( $code ) use ( $wp_error ) {
				if ( 'generic' === $code ) {
					$message = $wp_error->get_error_message();
					$data    = $wp_error->get_error_data();
					$line    = $message;
				} else {
					$message = $wp_error->get_error_message( $code );
					$data    = $wp_error->get_error_data( $code );
					$line    = "[{$code}] {$message}";
				}

				if ( null !== $data ) {
					$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
					if ( false !== $json ) {
						$line .= "\nData: " . $json;
					} else {
						$line .= "\nData: [" . gettype( $data ) . ': Unable to encode]';
					}
				}

				return $line;
			},
			$codes
		);

		// Combine all lines into a single log entry with new line separation.
		$combined = implode( "\n\n", $lines );

		// Prevent extremely large log entries from overwhelming the DB.
		$max = 65535;
		if ( strlen( $combined ) > $max ) {
			$combined = substr( $combined, 0, $max - 24 ) . "\n\n...truncated...";
		}

		self::log( $component, $level, $combined, $user_id, $sol_id );
	}

	/**
	 * Logs an emergency-level message.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param string       $message Emergency message.
	 * @param int|null     $user_id Optional. WP user ID. Default null.
	 * @param string|null  $sol_id Optional. SOL identifier. Default null.
	 */
	public static function emergency( LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null ): void {
		self::log( $component, LogLevel::EMERGENCY, $message, $user_id, $sol_id );
	}

	/**
	 * Logs an alert-level message.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param string       $message Alert message.
	 * @param int|null     $user_id Optional. WP user ID. Default null.
	 * @param string|null  $sol_id Optional. SOL identifier. Default null.
	 */
	public static function alert( LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null ): void {
		self::log( $component, LogLevel::ALERT, $message, $user_id, $sol_id );
	}

	/**
	 * Logs a critical-level message.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param string       $message Critical message.
	 * @param int|null     $user_id Optional. WP user ID. Default null.
	 * @param string|null  $sol_id Optional. SOL identifier. Default null.
	 */
	public static function critical( LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null ): void {
		self::log( $component, LogLevel::CRITICAL, $message, $user_id, $sol_id );
	}

	/**
	 * Logs an error-level message.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param string       $message Error message.
	 * @param int|null     $user_id Optional. WP user ID. Default null.
	 * @param string|null  $sol_id Optional. SOL identifier. Default null.
	 */
	public static function error( LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null ): void {
		self::log( $component, LogLevel::ERROR, $message, $user_id, $sol_id );
	}

	/**
	 * Logs a warning-level message.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param string       $message Warning message.
	 * @param int|null     $user_id Optional. WP user ID. Default null.
	 * @param string|null  $sol_id Optional. SOL identifier. Default null.
	 */
	public static function warning( LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null ): void {
		self::log( $component, LogLevel::WARNING, $message, $user_id, $sol_id );
	}

	/**
	 * Logs a notice-level message.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param string       $message Notice message.
	 * @param int|null     $user_id Optional. WP user ID. Default null.
	 * @param string|null  $sol_id Optional. SOL identifier. Default null.
	 */
	public static function notice( LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null ): void {
		self::log( $component, LogLevel::NOTICE, $message, $user_id, $sol_id );
	}

	/**
	 * Logs an informational message.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param string       $message Informational message.
	 * @param int|null     $user_id Optional. WP user ID. Default null.
	 * @param string|null  $sol_id Optional. SOL identifier. Default null.
	 */
	public static function info( LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null ): void {
		self::log( $component, LogLevel::INFO, $message, $user_id, $sol_id );
	}

	/**
	 * Logs a debug-level message.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param string       $message Debug message.
	 * @param int|null     $user_id Optional. WP user ID to associate with this message. Default null.
	 * @param string|null  $sol_id Optional. SOL identifier to associate with this message. Default null.
	 */
	public static function debug( LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null ): void {
		self::log( $component, LogLevel::DEBUG, $message, $user_id, $sol_id );
	}

	/**
	 * Checks whether the logs table currently exists.
	 *
	 * @since 2.4.0
	 *
	 * @param string $logs_table Full logs table name.
	 * @return bool Whether the operation succeeds.
	 */
	private function scouting_oidc_logger_table_exists( string $logs_table ): bool {
		global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $logs_table ) );

		return is_string( $found_table ) && $found_table === $logs_table;
	}

	/**
	 * Whether debug-level entries are enabled for plugin database logging.
	 *
	 * @since 2.4.0
	 *
	 * @return bool Whether the operation succeeds.
	 */
	private static function is_debug_logging_enabled(): bool {
		return (bool) get_option( 'scouting_oidc_debug_logging_enabled', false );
	}

	/**
	 * Mirrors plugin logs to the WordPress/PHP error log when WP_DEBUG is enabled.
	 *
	 * @since 2.4.0
	 *
	 * @param LogComponent $component Component for this log entry.
	 * @param LogLevel     $level Severity level for this log entry.
	 * @param string       $message Log message content.
	 * @param int|null     $user_id Optional WP user ID.
	 * @param string|null  $sol_id Optional SOL identifier.
	 */
	private static function maybe_log_to_wp_debug( LogComponent $component, LogLevel $level, string $message, ?int $user_id, ?string $sol_id ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$prefix        = '[Scouting OIDC] [' . strtoupper( $level->value ) . '] [' . strtoupper( $component->value ) . ']';
		$context_parts = array();

		if ( null !== $user_id ) {
			$context_parts[] = 'user_id=' . (string) $user_id;
		}

		if ( null !== $sol_id && '' !== $sol_id ) {
			$context_parts[] = 'sol_id=' . $sol_id;
		}

		$context = empty( $context_parts ) ? '' : ' [' . implode( ' ', $context_parts ) . ']';

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $prefix . $context . ' ' . $message );
	}
}
