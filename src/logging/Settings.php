<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class LoggingSettings
{
    /**
     * Register screen options for the logging admin page.
     *
     * @return void
     */
    public function register_screen_options(): void {
        add_screen_option(
            'per_page',
            [
                'label' => __('Logs per page', 'scouting-openid-connect'),
                'default' => 20,
                'option' => 'scouting_oidc_logs_per_page',
            ]
        );
    }

    /**
     * Register available columns for the logging screen options panel.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public function register_screen_columns(array $columns): array {
        return [
            'created_at' => __('Date/Time', 'scouting-openid-connect'),
            'level' => __('Level', 'scouting-openid-connect'),
            'type' => __('Type', 'scouting-openid-connect'),
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
    public function set_screen_option(mixed $status, string $option, mixed $value): mixed {
        if ($option !== 'scouting_oidc_logs_per_page') {
            return $status;
        }

        $per_page = absint((int) $value);
        if ($per_page < 1) {
            $per_page = 20;
        }

        return min($per_page, 500);
    }
}