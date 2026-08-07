<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class LoggingHelp
{
    /**
     * Register contextual help tabs for the logging screen.
     *
     * @return void
     */
    public function scouting_oidc_logging_register_help_tabs(): void {
        $screen = get_current_screen();
        if (!($screen instanceof \WP_Screen)) {
            return;
        }

        $screen->add_help_tab([
            'id' => 'scouting_oidc_logging_help_overview',
            'title' => __('Overview', 'scouting-openid-connect'),
            'content' =>
                '<p>' . esc_html__('This screen shows the plugin logs stored in the database table scouting_oidc_logs.', 'scouting-openid-connect') . '</p>' .
                '<p>' . esc_html__('Each row contains a timestamp, severity level, component, optional user details, and the log message.', 'scouting-openid-connect') . '</p>' .
                '<p>' . esc_html__('Log timestamps are stored in UTC in the database and displayed using the WordPress site timezone.', 'scouting-openid-connect') . '</p>'
        ]);

        $screen->add_help_tab([
            'id' => 'scouting_oidc_logging_help_filters',
            'title' => __('Filtering and Search', 'scouting-openid-connect'),
            'content' =>
                '<p>' . esc_html__('Use the filter controls to narrow results by date/time range, level, component, user ID or SOL ID.', 'scouting-openid-connect') . '</p>' .
                '<p>' . esc_html__('Use the search box to find text in the message field.', 'scouting-openid-connect') . '</p>' .
                '<p>' . esc_html__('The Reset button clears filters and returns to the latest entries.', 'scouting-openid-connect') . '</p>'
        ]);

        $screen->add_help_tab([
            'id' => 'scouting_oidc_logging_help_export_retention',
            'title' => __('Export and Retention', 'scouting-openid-connect'),
            'content' =>
                '<p>' . esc_html__('Use the Download .log button to export the currently filtered logs.', 'scouting-openid-connect') . '</p>' .
                '<p>' . esc_html(
                    sprintf(
                        /* translators: %d: Number of days log entries are kept before cleanup. */
                        __('A daily cleanup task removes log entries older than %d day(s).', 'scouting-openid-connect'),
                        CronJobs::scouting_oidc_get_log_retention_days()
                    )
                ) . '</p>'
        ]);

        $screen->add_help_tab([
            'id' => 'scouting_oidc_logging_help_debug',
            'title' => __('Debug Logging', 'scouting-openid-connect'),
            'content' =>
                '<p>' . esc_html__('Debug-level entries are stored only when Enable debug logs is checked in General Settings.', 'scouting-openid-connect') . '</p>' .
                '<p>' . esc_html__('When WP_DEBUG is enabled, plugin log messages are also mirrored to the WordPress/PHP error log.', 'scouting-openid-connect') . '</p>'
        ]);

        $screen->set_help_sidebar(
            '<p><strong>' . esc_html__('Need more help?', 'scouting-openid-connect') . '</strong></p>' .
            '<p><a href="' . esc_url(admin_url('admin.php?page=scouting-oidc-support')) . '">' . esc_html__('Open the Support page', 'scouting-openid-connect') . '</a></p>'
        );
    }
}
