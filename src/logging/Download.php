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

        $filename = 'scouting-oidc-logs-' . date('d-m-Y-H-i-s') . '.log';

        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        $output = fopen('php://output', 'wb');
        if ($output === false) {
            wp_die(esc_html__('Unable to generate log export output.', 'scouting-openid-connect'));
        }

        fwrite($output, "# Scouting OIDC logs export by user ID " . get_current_user_id() . "\n");
        fwrite($output, "# Website: " . home_url() . "\n");
        fwrite($output, "# UTC time of export: " . gmdate('Y-m-d H:i:s') . "\n");
        fwrite($output, "# Wordpress time of export: " . current_time('Y-m-d H:i:s e') . "\n\n");
        fwrite($output, "# Filters applied:\n");
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

            fwrite($output, "# - " . esc_html($key) . ": " . esc_html($display) . "\n");
        }
        fwrite($output, "\n");

        $sorting = [
            'orderby' => 'id',
            'order' => 'asc',
        ];
        $chunk_size = 999;
        $offset = 0;

        while (true) {
            $rows = $logging->get_logs($filters, $sorting, $chunk_size, $offset);
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                fwrite($output, $this->format_log_line($row));
            }

            if (count($rows) < $chunk_size) {
                break;
            }

            $offset += $chunk_size;
        }

        fclose($output);
        exit;
    }

    /**
     * Format one log row as a single .log line.
     *
     * @param array<string, mixed> $row
     * @return string
     */
    private function format_log_line(array $row): string {
        $created_at = (string) $row['created_at'];
        $level = strtoupper((string) ($row['level'] ?? 'unknown'));
        $component = strtoupper((string) ($row['component'] ?? 'unknown'));

        $user_id = isset($row['user_id']) && (int) $row['user_id'] > 0
            ? (string) ((int) $row['user_id'])
            : '-';

        $sol_id = isset($row['sol_id']) && trim((string) $row['sol_id']) !== ''
            ? trim((string) $row['sol_id'])
            : '-';

        $message = (string) ($row['message'] ?? '');
        $message = str_replace(["\r\n", "\r", "\n"], '\\n', $message);
        if ($message === '') {
            $message = '-';
        }

        return sprintf(
            "[%s] [%s] [%s] user_id=%s sol_id=%s %s\n",
            $created_at,
            $level,
            $component,
            $user_id,
            $sol_id,
            $message
        );
    }
}
