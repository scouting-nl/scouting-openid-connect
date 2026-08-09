<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Cron utilities for Scouting OIDC maintenance jobs.
 */
class CronJobs {
	/**
	 * Cron hook used for daily log cleanup.
	 */
	public const CLEANUP_CRON_HOOK = 'scouting_oidc_logs_cleanup_daily';

	/**
	 * Admin action used to run log cleanup manually.
	 */
	public const RUN_CLEANUP_ACTION = 'scouting_oidc_run_log_cleanup';

	/**
	 * Default number of days to retain logs.
	 */
	private const DEFAULT_LOG_RETENTION_DAYS = 30;

	/**
	 * Minimum allowed number of days to retain logs.
	 */
	private const MIN_LOG_RETENTION_DAYS = 1;

	/**
	 * Maximum allowed number of days to retain logs.
	 */
	private const MAX_LOG_RETENTION_DAYS = 3650;

	/**
	 * Option key for log retention days.
	 */
	private const LOG_RETENTION_OPTION = 'scouting_oidc_log_retention_days';

	/**
	 * Option key for the last successful cleanup timestamp.
	 */
	private const LAST_CLEANUP_OPTION = 'scouting_oidc_last_log_cleanup';

	/**
	 * Schedule cleanup jobs when plugin is activated.
	 *
	 * @return void
	 */
	public function scouting_oidc_cron_activate(): void {
		$this->scouting_oidc_logger_schedule_cleanup();
		Logger::info( LogComponent::CRONJOB, 'Cron activation completed and cleanup schedule checked.' );
	}

	/**
	 * Remove scheduled cleanup job when plugin is deactivated.
	 *
	 * @return void
	 */
	public function scouting_oidc_cron_deactivate(): void {
		$cleared = wp_clear_scheduled_hook( self::CLEANUP_CRON_HOOK );

		if ( $cleared === false ) {
			Logger::warning( LogComponent::CRONJOB, 'Failed to clear cleanup cron hook during deactivation.' );
			return;
		}

		Logger::info( LogComponent::CRONJOB, 'Cron deactivation cleared cleanup schedule.' );
	}

	/**
	 * Ensure the daily cleanup event exists.
	 *
	 * @return bool Whether a valid daily event exists or was scheduled.
	 */
	public function scouting_oidc_logger_schedule_cleanup(): bool {
		$event = wp_get_scheduled_event( self::CLEANUP_CRON_HOOK );
		if ( is_object( $event ) && $event->schedule === 'daily' ) {
			return true;
		}

		$can_schedule = true;
		if ( is_object( $event ) ) {
			$cleared = wp_clear_scheduled_hook( self::CLEANUP_CRON_HOOK );
			if ( $cleared === false ) {
				Logger::warning( LogComponent::CRONJOB, 'Failed to clear an invalid cleanup cron schedule.' );
				$can_schedule = false;
			} else {
				Logger::warning( LogComponent::CRONJOB, 'Invalid cleanup cron schedule cleared before rescheduling.' );
			}
		}

		if ( $can_schedule ) {
			$scheduled = wp_schedule_event(
				$this->get_next_cleanup_timestamp(),
				'daily',
				self::CLEANUP_CRON_HOOK,
				array(),
				true
			);

			if ( is_wp_error( $scheduled ) ) {
				Logger::log_wp_error( LogComponent::CRONJOB, LogLevel::ERROR, $scheduled );
				$can_schedule = false;
			} else {
				Logger::info( LogComponent::CRONJOB, 'Cleanup cron event scheduled successfully.' );
			}
		}

		return $can_schedule;
	}

	/**
	 * Delete log rows older than the configured retention period.
	 *
	 * @return bool Whether the cleanup query succeeded.
	 */
	public static function scouting_oidc_logger_cleanup_old_logs(): bool {
		global $wpdb;

		$logs_table     = $wpdb->prefix . 'scouting_oidc_logs';
		$retention_days = self::scouting_oidc_get_log_retention_days();

		// Compare against UTC so the retention window matches the UTC timestamp stored in the table.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < (UTC_TIMESTAMP(3) - INTERVAL %d DAY)',
				$logs_table,
				$retention_days
			)
		);

		if ( $result === false ) {
			Logger::error( LogComponent::CRONJOB, 'Failed to delete old log rows during cleanup.' );
			return false;
		}

		update_option( self::LAST_CLEANUP_OPTION, time(), false );
		Logger::info( LogComponent::CRONJOB, 'Cron cleanup removed ' . (string) $result . ' old log row(s) older than ' . (string) $retention_days . ' day(s).' );
		return true;
	}

	/**
	 * Run cleanup immediately from the Site Health recovery action.
	 *
	 * @return void
	 */
	public function scouting_oidc_logger_run_cleanup_now(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to run Scouting OpenID Connect log cleanup.', 'scouting-openid-connect' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::RUN_CLEANUP_ACTION );

		if ( self::scouting_oidc_logger_cleanup_old_logs() ) {
			$this->scouting_oidc_logger_reset_cleanup_schedule();
		}

		wp_safe_redirect( admin_url( 'site-health.php' ) );
		exit;
	}

	/**
	 * Return the timestamp of the last successful cleanup.
	 *
	 * @return int|null
	 */
	public static function scouting_oidc_get_last_cleanup_timestamp(): ?int {
		$timestamp = get_option( self::LAST_CLEANUP_OPTION, 0 );

		return is_numeric( $timestamp ) && (int) $timestamp > 0 ? (int) $timestamp : null;
	}

	/**
	 * Get the configured log retention period in days.
	 *
	 * @return int
	 */
	public static function scouting_oidc_get_log_retention_days(): int {
		$configured_retention_days = get_option( self::LOG_RETENTION_OPTION, self::DEFAULT_LOG_RETENTION_DAYS );

		if ( ! is_numeric( $configured_retention_days ) ) {
			return self::DEFAULT_LOG_RETENTION_DAYS;
		}

		$retention_days = (int) $configured_retention_days;

		if ( $retention_days < self::MIN_LOG_RETENTION_DAYS ) {
			return self::MIN_LOG_RETENTION_DAYS;
		}

		if ( $retention_days > self::MAX_LOG_RETENTION_DAYS ) {
			return self::MAX_LOG_RETENTION_DAYS;
		}

		return $retention_days;
	}

	/**
	 * Replace the current cleanup event with a fresh daily schedule.
	 *
	 * @return bool Whether the replacement schedule was created.
	 */
	private function scouting_oidc_logger_reset_cleanup_schedule(): bool {
		$cleared = wp_clear_scheduled_hook( self::CLEANUP_CRON_HOOK );

		if ( $cleared === false ) {
			Logger::warning( LogComponent::CRONJOB, 'Manual cleanup succeeded, but its old cron schedule could not be cleared.' );
			return false;
		}

		return $this->scouting_oidc_logger_schedule_cleanup();
	}

	/**
	 * Compute the next run timestamp around 02:30 in the site timezone.
	 *
	 * @return int
	 */
	private function get_next_cleanup_timestamp(): int {
		$timezone = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $timezone );
		$next_run = $now->setTime( 2, 30, 0 );

		if ( $next_run <= $now ) {
			$next_run = $next_run->modify( '+1 day' );
		}

		return $next_run->getTimestamp();
	}
}
