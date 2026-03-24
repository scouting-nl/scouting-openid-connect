<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Cron utilities for Scouting OIDC maintenance jobs.
 */
class CronJobs {
    /**
     * Cron hook used for daily log cleanup.
     */
    public const CLEANUP_CRON_HOOK = 'scouting_oidc_logs_cleanup_daily';

    /**
     * Number of days to retain logs.
     */
    private const LOG_RETENTION_DAYS = 30;

    /**
     * Schedule cleanup jobs when plugin is activated.
     *
     * @return void
     */
    public function scouting_oidc_cron_activate(): void {
        $this->scouting_oidc_logger_schedule_cleanup();
        Logger::info(LogComponent::CRONJOB, 'Cron activation completed and cleanup schedule checked.');
    }

    /**
     * Remove scheduled cleanup job when plugin is deactivated.
     *
     * @return void
     */
    public function scouting_oidc_cron_deactivate(): void {
        $cleared = wp_clear_scheduled_hook(self::CLEANUP_CRON_HOOK);

        if ($cleared === false) {
            Logger::warning(LogComponent::CRONJOB, 'Failed to clear cleanup cron hook during deactivation.');
            return;
        }

        Logger::info(LogComponent::CRONJOB, 'Cron deactivation cleared cleanup schedule.');
    }

    /**
     * Ensure the daily cleanup event exists.
     *
     * @return void
     */
    public function scouting_oidc_logger_schedule_cleanup(): void {
        if (wp_next_scheduled(self::CLEANUP_CRON_HOOK) !== false) {
            return;
        }

        $scheduled = wp_schedule_event($this->get_next_cleanup_timestamp(), 'daily', self::CLEANUP_CRON_HOOK);

        if (is_wp_error($scheduled)) {
            Logger::log_wp_error(LogComponent::CRONJOB, LogLevel::ERROR, $scheduled);
            return;
        }

        if ($scheduled === false) {
            Logger::warning(LogComponent::CRONJOB, 'Cleanup cron event was not scheduled.');
            return;
        }

        Logger::info(LogComponent::CRONJOB, 'Cleanup cron event scheduled successfully.');
    }

    /**
     * Delete log rows older than the configured retention period.
     *
     * @return void
     */
    public static function scouting_oidc_logger_cleanup_old_logs(): void {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'scouting_oidc_logs';

        // Compare against UTC so the retention window matches the UTC timestamp stored in the table.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE created_at < (UTC_TIMESTAMP(3) - INTERVAL %d DAY)',
                $logs_table,
                self::LOG_RETENTION_DAYS
            )
        );

        if ($result === false) {
            Logger::error(LogComponent::CRONJOB, 'Failed to delete old log rows during cleanup.');
            return;
        }

        Logger::info(LogComponent::CRONJOB, 'Cron cleanup removed ' . (string) $result . ' old log row(s).');
    }

    /**
     * Compute the next run timestamp around 02:30 in the site timezone.
     *
     * @return int
     */
    private function get_next_cleanup_timestamp(): int {
        $timezone = wp_timezone();
        $now = new \DateTimeImmutable('now', $timezone);
        $next_run = $now->setTime(2, 30, 0);

        if ($next_run <= $now) {
            $next_run = $next_run->modify('+1 day');
        }

        return $next_run->getTimestamp();
    }
}
