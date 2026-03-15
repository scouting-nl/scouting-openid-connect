<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use WP_Error;

/**
 * Log levels for the logging system (PSR-3 compatible).
 *
 * This enum lists the supported severity levels (PSR-3) stored in the database.
 */
enum LogLevel: string {
    case EMERGENCY = 'emergency';
    case ALERT     = 'alert';
    case CRITICAL  = 'critical';
    case ERROR     = 'error';
    case WARNING   = 'warning';
    case NOTICE    = 'notice';
    case INFO      = 'info';
    case DEBUG     = 'debug';
}

/**
 * Log components for categorizing log entries.
 * 
 * This enum can be extended in the future to include additional log components as needed. Each log entry must have a component, which allows for easier filtering and analysis of logs based on their category.
 */
enum LogComponent: string {
    case ASSETS = 'assets';
    case AUTH = 'auth';
    case CRONJOB = 'cronjob';
    case MAIL = 'mail';
    case OIDC = 'oidc';
    case SETTINGS = 'settings';
    case USER = 'user';
}

/**
 * Database-backed logging helper for Scouting OIDC.
 *
 * Provides convenience wrappers for logging at various severity levels and
 * a small installer to create the underlying logs table.
 */
class Logger {
    /**
     * Create or update the logs table during plugin activation.
     *
     * @return void
     */
    public function scouting_oidc_logger_database_create(): void {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'scouting_oidc_logs';
        $charset_collate = $wpdb->get_charset_collate();

        // Build SQL ENUM values from the LogComponent enum cases
        $enum_component_values = "'" . implode("','", array_map(
            fn($case) => $case->value,
            LogComponent::cases()
        )) . "'";

        // Build SQL ENUM values from the LogLevel enum cases
        $enum_level_values = "'" . implode("','", array_map(
            fn($case) => $case->value,
            LogLevel::cases()
        )) . "'";

        // Create the logs table with appropriate columns and basic indexes.
        $sql = "CREATE TABLE {$logs_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL,
            sol_id VARCHAR(60) NULL,
            component ENUM($enum_component_values) NOT NULL,
            level ENUM($enum_level_values) NOT NULL,
            created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
            message TEXT NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY sol_id (sol_id),
            KEY component (component),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Ensure the table engine supports foreign keys (InnoDB).
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB
        $wpdb->query( "ALTER TABLE `{$logs_table}` ENGINE=InnoDB" );

        // Only add FK if it doesn't already exist
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing_fk = $wpdb->get_var(
            $wpdb->prepare(
                "
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = %s
                AND COLUMN_NAME = %s
                AND REFERENCED_TABLE_NAME = %s
                ",
                $logs_table,
                'user_id',
                $wpdb->users
            )
        );

        if (!$existing_fk) {
            // Add a foreign key constraint on user_id referencing the WP users table, with cascading deletes to maintain referential integrity. This ensures that if a user is deleted from WordPress, all their associated log entries will also be removed, preventing orphaned log records.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB
            $wpdb->query( "ALTER TABLE `{$logs_table}` ADD CONSTRAINT fk_scouting_logs_user FOREIGN KEY (user_id) REFERENCES `{$wpdb->users}`(ID) ON DELETE CASCADE" );
        }

        // Set option to track the installed DB version for future upgrades.
        update_option('scouting_oidc_db_version', SCOUTING_OIDC_VERSION);
    }

    /**
     * Persist a log entry to the database.
     *
     * @param LogComponent $component Component for this log entry.
     * @param LogLevel $level Severity level for this log entry.
     * @param string $message Log message content.
     * @param int|null $user_id Optional WP user ID to associate with this entry.
     * @param string|null $sol_id Optional SOL identifier to associate with this entry.
     * @return void
     */
    private static function log(LogComponent $component, LogLevel $level, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        global $wpdb;

        // If $user_id is not provided, attempt to use the current user's ID if available.
        if ($user_id === null) {
            $user_id = get_current_user_id();

            // `get_current_user_id()` returns 0 when no user is available; convert 0 to null
            if ($user_id === 0) {
                $user_id = null;
            }
        }
        else {
            // If a $user_id is provided, verify that it corresponds to an existing user.
            $user_exists = get_user_by('ID', $user_id);
            if ($user_exists === false) {
                // If the user ID does not correspond to a real user, clear it so
                // we don't store an invalid user_id in the logs.
                $user_id = null;
            }
        }

        // If $sol_id is not provided and we have a valid $user_id, attempt to use the user's login as the SOL ID.
        if (empty($sol_id) && $user_id !== null) {
            $user = get_userdata($user_id);

            // Check $user is valid and has a user_login before using it as sol_id
            if ($user !== false && !empty($user->user_login)) {
                $sol_id = $user->user_login;
            }
            else {
                // If we can't derive a valid sol_id, set it to null to avoid storing empty strings.
                $sol_id = null;
            }
        }
        else {
            // If a $sol_id is provided, ensure it's a non-empty string. If it's empty, set it to null.
            if (is_string($sol_id) && trim($sol_id) === '') {
                $sol_id = null;
            }
        }

        // Insert the log entry. Format specifiers ensure proper data typing.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $wpdb->prefix . 'scouting_oidc_logs',
            [
                'component' => $component->value,
                'level' => $level->value,
                'message' => $message,
                'user_id' => $user_id,
                'sol_id' => $sol_id,
            ],
            [
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
            ]
        );
    }

    /**
     * Log a WP_Error object at the error level, including all error codes and messages in the log entry.
     *
     * @param LogComponent $component Component for this log entry.
     * @param LogLevel $level Severity level for this log entry.
     * @param WP_Error $wp_error The WP_Error object to log.
     * @param int|null $user_id Optional WP user ID to associate with this error.
     * @param string|null $sol_id Optional SOL identifier to associate with this error.
     * @return void
     */
    public static function log_wp_error(LogComponent $component, LogLevel $level, WP_Error $wp_error, ?int $user_id = null, ?string $sol_id = null): void {
        $codes = $wp_error->get_error_codes();

        // Normalize to a codes array so we have a single processing path.
        if (empty($codes)) {
            $codes = ['generic'];
        }

        // Build log lines for each error code, including the generic message if no specific codes are present.
        $lines = array_map(function ($code) use ($wp_error) {
            if ($code === 'generic') {
                $message = $wp_error->get_error_message();
                $data = $wp_error->get_error_data();
                $line = $message;
            } else {
                $message = $wp_error->get_error_message($code);
                $data = $wp_error->get_error_data($code);
                $line = "[{$code}] {$message}";
            }

            if ($data !== null) {
                $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                if ($json !== false) {
                    $line .= "\nData: " . $json;
                } else {
                    $line .= "\nData: [" . gettype($data) . ": Unable to encode]";
                }
            }

            return $line;
        }, $codes);

        // Combine all lines into a single log entry with new line separation.
        $combined = implode("\n\n", $lines);

        // Prevent extremely large log entries from overwhelming the DB.
        $max = 65535; // safe default for TEXT fields
        if (strlen($combined) > $max) {
            $combined = substr($combined, 0, $max - 24) . "\n\n...truncated...";
        }

        self::log($component, $level, $combined, $user_id, $sol_id);
    }

    /**
     * Log an emergency-level message.
     *
     * @param LogComponent $component Component for this log entry.
     * @param string $message Emergency message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function emergency(LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($component, LogLevel::EMERGENCY, $message, $user_id, $sol_id);
    }

    /**
     * Log an alert-level message.
     *
     * @param LogComponent $component Component for this log entry.
     * @param string $message Alert message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function alert(LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($component, LogLevel::ALERT, $message, $user_id, $sol_id);
    }

    /**
     * Log a critical-level message.
     *
     * @param LogComponent $component Component for this log entry.
     * @param string $message Critical message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function critical(LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($component, LogLevel::CRITICAL, $message, $user_id, $sol_id);
    }

    /**
     * Log an error-level message.
     *
     * @param LogComponent $component Component for this log entry.
     * @param string $message Error message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function error(LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($component, LogLevel::ERROR, $message, $user_id, $sol_id);
    }

    /**
     * Log a warning-level message.
     *
     * @param LogComponent $component Component for this log entry.
     * @param string $message Warning message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function warning(LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($component, LogLevel::WARNING, $message, $user_id, $sol_id);
    }

    /**
     * Log a notice-level message.
     *
     * @param LogComponent $component Component for this log entry.
     * @param string $message Notice message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function notice(LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($component, LogLevel::NOTICE, $message, $user_id, $sol_id);
    }

    /**
     * Log an informational message.
     *
     * @param LogComponent $component Component for this log entry.
     * @param string $message Informational message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function info(LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($component, LogLevel::INFO, $message, $user_id, $sol_id);
    }

    /**
     * Log a debug-level message.
     *
     * @param LogComponent $component Component for this log entry.
     * @param string $message Debug message.
     * @param int|null $user_id Optional WP user ID to associate with this message.
     * @param string|null $sol_id Optional SOL identifier to associate with this message.
     * @return void
     */
    public static function debug(LogComponent $component, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($component, LogLevel::DEBUG, $message, $user_id, $sol_id);
    }
}