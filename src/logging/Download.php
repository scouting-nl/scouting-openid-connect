<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class LoggingDownload
{
    /**
     * Trigger a .log download for the currently filtered logs.
     *
     * @param Logging $logging
     * @param array<string, mixed> $filters
     * @return void
     */
    public function download(Logging $logging, array $filters): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to export logs.', 'scouting-openid-connect'));
        }

        check_admin_referer('scouting_oidc_logs_filter', 'scouting_oidc_logs_filter_nonce');

        $filename = 'scouting-oidc-logs-' . current_time('d-m-Y-H-i-s') . '.log';

        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        $output = '';
        $output .= "# Scouting OIDC logs export by user ID " . get_current_user_id() . "\n";
        $output .= "# Website: " . home_url() . "\n";
        $output .= "# UTC time of export: " . gmdate('Y-m-d H:i:s') . "\n";
        $output .= "# Wordpress time of export: " . current_time('Y-m-d H:i:s e') . "\n\n";
        $output .= "# Filters applied:\n";
        foreach ($filters as $key => $value) {
            if ($key === 'user_id') {
                if ($value === 0) {
                    $display = '';
                } 
                else {
                    $user_info = get_userdata((int) $value);
                    if ($user_info !== false) {
                        $display = sprintf('%d (%s)', (int) $value, $user_info->display_name);
                    } else {
                        $display = (string) $value;
                    }
                }
            } else if ($key === 'sol_id') {
                $user_info = get_user_by('login', (string) $value);
                if ($user_info !== false) {
                    $display = sprintf('%s (%s)', (string) $value, $user_info->display_name);
                } else {
                    $display = (string) $value;
                }
            } else {
                if (is_array($value)) {
                    $display = '(' . implode(', ', $value) . ')';
                } else {
                    $display = (string) $value;
                }
            }

            $output .= "# - " . esc_html($key) . ": " . esc_html($display) . "\n";
        }
        $output .= "\n";

        $sorting = [
            'orderby' => 'id',
            'order' => 'asc',
        ];

        $output .= "Format: [created_at] [level] [component] [user_id] [sol_id] message\n\n";

        // Fetch and output logs
        $rows = $logging->get_logs($filters, $sorting);
        $padding = $this->determine_padding($rows);
        if (empty($rows)) {
            $output .= "# No logs found for the applied filters.\n";
        } else  {
            foreach ($rows as $row) {
                $output .= $this->format_log_line($row, $padding);
            }
        }

        echo $output;
        exit;
    }

    /**
     * Determine the necessary padding length for a given field based on the log rows.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function determine_padding(array $rows): array {
        $max_level_length = 0;
        $max_component_length = 0;
        $max_user_id_length = 0;
        $max_sol_id_length = 0;

        foreach ($rows as $row) {
            $level_length = isset($row['level']) ? strlen((string) $row['level']) : 0;
            if ($level_length > $max_level_length) {
                $max_level_length = $level_length;
            }

            $component_length = isset($row['component']) ? strlen((string) $row['component']) : 0;
            if ($component_length > $max_component_length) {
                $max_component_length = $component_length;
            }

            $user_id_length = isset($row['user_id']) ? strlen((string) $row['user_id']) : 0;
            if ($user_id_length > $max_user_id_length) {
                $max_user_id_length = $user_id_length;
            }

            $sol_id_length = isset($row['sol_id']) ? strlen((string) $row['sol_id']) : 0;
            if ($sol_id_length > $max_sol_id_length) {
                $max_sol_id_length = $sol_id_length;
            }
        }

        // Set minimum padding lengths
        return [
            'created_at' => 23, // "dd-mm-yyyy hh:mm:ss.fff"
            'level' => $max_level_length,
            'component' => $max_component_length,
            'user_id' => $max_user_id_length,
            'sol_id' => $max_sol_id_length,
        ];
    }

    /**
     * Format one log row as a single .log line.
     *
     * @param array<string, mixed> $row
     * @param array<string, int> $padding
     * @return string
     */
    private function format_log_line(array $row, array $padding): string {
        // Render the stored UTC timestamp in the current site timezone.
        $created_at = Logger::scouting_oidc_format_utc_datetime_for_site((string) ($row['created_at'] ?? ''));
        $level = strtoupper((string) ($row['level'] ?? 'unknown'));
        $component = strtoupper((string) ($row['component'] ?? 'unknown'));

        $user_id = isset($row['user_id']) && $row['user_id'] !== null && $row['user_id'] !== '' && (int) $row['user_id'] > 0
            ? (string) ((int) $row['user_id'])
            : ' ';

        $sol_id = isset($row['sol_id']) && trim((string) $row['sol_id']) !== ''
            ? trim((string) $row['sol_id'])
            : ' ';

        $message = (string) ($row['message'] ?? '');
        $message = str_replace(["\r\n", "\r", "\n"], '\\n', $message);
        if ($message === '') {
            $message = '-';
        }

        // Fixed-width/padded fields for monospaced alignment in .log
        $created_at_f = str_pad($created_at, $padding['created_at']);
        $level_f = str_pad($level, $padding['level']);
        $component_f = str_pad($component, $padding['component']);
        $user_id_f = str_pad($user_id, $padding['user_id']);
        $sol_id_f = str_pad($sol_id, $padding['sol_id']);

        return sprintf("[%s] [%s] [%s] [%s] [%s] %s\n", $created_at_f, $level_f, $component_f, $user_id_f, $sol_id_f, $message);
    }
}
