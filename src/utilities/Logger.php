<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use WP_Error;

/**
 * Log levels for the logging system.
 *
 * This enum lists the supported severity levels stored in the database.
 */
enum LogLevel: string {
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
    case CRITICAL = 'critical';
}

/**
 * Log types for categorizing log entries.
 * 
 * This enum can be extended in the future to include additional log types as needed. Each log entry must have a type, which allows for easier filtering and analysis of logs based on their category.
 */

enum LogType: string {
    case MAIL = 'MAIL'; 
}

/**
 * Database-backed logging helper for Scouting OIDC.
 *
 * Provides convenience wrappers for logging at various severity levels and
 * a small installer to create the underlying logs table.
 */
class Logger {

    /**
     * Get the logs table name using the WP DB prefix.
     *
     * @return string Fully qualified logs table name.
     */
    private static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'scouting_oidc_logs';
    }

    /**
     * Create or update the logs table during plugin activation.
     *
     * @return void
     */
    public function scouting_oidc_logger_install(): void {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        // Build SQL ENUM values from the LogType enum cases
        $enum_type_values = "'" . implode("','", array_map(
            fn($case) => $case->value,
            LogType::cases()
        )) . "'";

        // Build SQL ENUM values from the LogLevel enum cases
        $enum_level_values = "'" . implode("','", array_map(
            fn($case) => $case->value,
            LogLevel::cases()
        )) . "'";

        // Create the logs table with appropriate columns and basic indexes.
        // Note: `sol_id` is stored as VARCHAR(60) and is not a foreign key. This allows us to log events related to SOL identifiers that may not have a corresponding WP user (e.g. failed login attempts with invalid SOL IDs).
        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NULL,
            sol_id VARCHAR(60) NULL,
            type ENUM($enum_type_values) NOT NULL,
            level ENUM($enum_level_values) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            message TEXT NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY sol_id (sol_id),
            KEY level (level),
            KEY created_at (created_at),
            CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Persist a log entry to the database.
     *
     * @param LogType $type Category/type for this log entry.
     * @param LogLevel $level Severity level for this log entry.
     * @param string $message Log message content.
     * @param int|null $user_id Optional WP user ID to associate with this entry.
     * @param string|null $sol_id Optional SOL identifier to associate with this entry.
     * @return void
     */
    private static function log(LogType $type, LogLevel $level, string $message, ?int $user_id = null, ?string $sol_id = null): void {
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

        $table_name = self::get_table_name();

        // Insert the log entry. Format specifiers ensure proper data typing.
        $wpdb->insert(
            $table_name,
            [
                'type' => $type->value,
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
     * Log a debug-level message.
     *
     * @param LogType $type Category/type for this log entry.
     * @param string $message Debug message.
     * @param int|null $user_id Optional WP user ID to associate with this message.
     * @param string|null $sol_id Optional SOL identifier to associate with this message.
     * @return void
     */
    public static function debug(LogType $type, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($type, LogLevel::DEBUG, $message, $user_id, $sol_id);
    }

    /**
     * Log an informational message.
     *
     * @param LogType $type Category/type for this log entry.
     * @param string $message Informational message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function info(LogType $type, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($type, LogLevel::INFO, $message, $user_id, $sol_id);
    }

    /**
     * Log a warning-level message.
     *
     * @param LogType $type Category/type for this log entry.
     * @param string $message Warning message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function warning(LogType $type, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($type, LogLevel::WARNING, $message, $user_id, $sol_id);
    }

    /**
     * Log an error-level message.
     *
     * @param LogType $type Category/type for this log entry.
     * @param string $message Error message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function error(LogType $type, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($type, LogLevel::ERROR, $message, $user_id, $sol_id);
    }

    /**
     * Log a critical-level message.
     *
     * @param LogType $type Category/type for this log entry.
     * @param string $message Critical message.
     * @param int|null $user_id Optional WP user ID.
     * @param string|null $sol_id Optional SOL identifier.
     * @return void
     */
    public static function critical(LogType $type, string $message, ?int $user_id = null, ?string $sol_id = null): void {
        self::log($type, LogLevel::CRITICAL, $message, $user_id, $sol_id);
    }
}