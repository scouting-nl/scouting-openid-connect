<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class LoggingSettings
{
    /**
     * Register screen-option persistence filter early in the request lifecycle.
     */
    public function __construct() {
        add_filter('set-screen-option', [$this, 'scouting_oidc_logs_set_screen_option'], 10, 3);
        add_filter('screen_settings', [$this, 'scouting_oidc_logs_preserve_filters_referer'], 10, 2);
    }

    /**
     * Keep active logging filters in the redirect target when applying screen options.
     *
     * @param string $screen_settings
     * @param mixed $screen
     * @return string
     */
    public function scouting_oidc_logs_preserve_filters_referer(string $screen_settings, mixed $screen): string {
        if (!($screen instanceof \WP_Screen)) {
            return $screen_settings;
        }

        $screen_id = is_string($screen->id) ? $screen->id : '';
        if ($screen_id === '' || !str_contains($screen_id, 'scouting-oidc-logging')) {
            return $screen_settings;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (!is_string($request_uri) || $request_uri === '') {
            return $screen_settings;
        }

        $referer = remove_query_arg('_wp_http_referer', $request_uri);

        return $screen_settings . '<input type="hidden" name="_wp_http_referer" value="' . esc_attr($referer) . '" />';
    }

    /**
     * Register screen options for the logging admin page.
     *
     * @return void
     */
    public function scouting_oidc_logs_register_screen_options(): void {
        // Add the screen option (per_page type)
        add_screen_option('per_page', [
            'label' => __('Logs per page', 'scouting-openid-connect'),
            'default' => 20,
            'option' => 'scouting_oidc_logs_per_page',
        ]);
    }

    /**
     * Register available columns for the logging screen options panel.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function scouting_oidc_logs_register_screen_columns(array $columns): array {
        return [
            'created_at' => __('Date/Time', 'scouting-openid-connect'),
            'level' => __('Level', 'scouting-openid-connect'),
            'component' => __('Component', 'scouting-openid-connect'),
            'user_id' => __('User ID', 'scouting-openid-connect'),
            'sol_id' => __('SOL ID', 'scouting-openid-connect'),
            'message' => __('Message', 'scouting-openid-connect'),
        ];
    }

    /**
     * Persist custom screen option values.
     *
     * @param mixed $status
     * @param string $option
     * @param mixed $value
     * @return mixed
     */
    public function scouting_oidc_logs_set_screen_option(mixed $status, string $option, mixed $value): mixed {
        if ($option === 'scouting_oidc_logs_per_page') {
            return min(max(absint($value), 1), 999);
        }

        return $status;
    }
}