<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class LoggingFilters
{
    /**
     * Get sorting options from the request.
     *
     * @return array<string, string>
     */
    public function get_sorting(): array {
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if ($nonce !== '' && !wp_verify_nonce($nonce, "scouting_oidc_logs_filter")) {
            return [
                'orderby' => 'id',
                'order' => 'desc',
            ];
        }

        $order = isset($_GET['order']) ? sanitize_key(wp_unslash($_GET['order'])) : 'desc';
        $order = $order === 'asc' ? 'asc' : 'desc';

        return [
            'orderby' => 'id',
            'order' => $order,
        ];
    }

    /**
     * Parse an HTML datetime-local value to a MySQL DATETIME string.
     *
     * @param string $value Raw datetime-local value.
     * @return string|null
     */
    private function parse_datetime_local(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Normalize milliseconds if missing
        if (!str_contains($value, '.')) {
            $value .= '.000';
        }

        try {
            $datetime = new \DateTimeImmutable($value);
            // Return in MySQL DATETIME(3) format with milliseconds
            return $datetime->format('Y-m-d H:i:s.v');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get and validate filter values from the request.
     *
     * @return array<string, mixed>
     */
    public function get_filters(): array {
        $component_values = array_map(static fn(LogComponent $case) => $case->value, LogComponent::cases());
        $level_values = array_map(static fn(LogLevel $case) => $case->value, LogLevel::cases());

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, "scouting_oidc_logs_filter")) {
            $default_levels = array_values(array_filter($level_values, fn($v) => $v !== 'debug'));

            return [
                'date_from' => '',
                'date_to' => '',
                'date_from_sql' => null,
                'date_to_sql' => null,
                'component' => $component_values,
                'level' => $default_levels,
                'sol_id' => '',
                'user_id' => 0,
                'search' => '',
            ];
        }

        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
        $sol_id = isset($_GET['sol_id']) ? sanitize_text_field(wp_unslash($_GET['sol_id'])) : '';
        $user_id = isset($_GET['user_id']) ? sanitize_text_field(wp_unslash($_GET['user_id'])) : '';
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $filter_applied = isset($_GET['filter_applied']);

        if ($filter_applied) {
            $level_raw = isset($_GET['level']) && is_array($_GET['level'])
                ? array_map('sanitize_text_field', wp_unslash($_GET['level']))
                : [];
            $component_raw = isset($_GET['component']) && is_array($_GET['component'])
                ? array_map('sanitize_text_field', wp_unslash($_GET['component']))
                : [];
        } else {
            // Default: all levels except debug, all components
            $level_raw = array_values(array_filter($level_values, fn($v) => $v !== 'debug'));
            $component_raw = $component_values;
        }

        $levels = array_values(array_filter($level_raw, fn($l) => in_array($l, $level_values, true)));
        $components = array_values(array_filter($component_raw, fn($c) => in_array($c, $component_values, true)));

        return [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'date_from_sql' => $this->parse_datetime_local($date_from),
            'date_to_sql' => $this->parse_datetime_local($date_to),
            'component' => $components,
            'level' => $levels,
            'sol_id' => trim($sol_id),
            'user_id' => ctype_digit($user_id) ? absint($user_id) : 0,
            'search' => trim($search),
        ];
    }

    /**
     * Build SQL WHERE for filters.
     *
     * @param array<string, mixed> $filters
     * @param array<int, mixed> $values
     * @return string
     */
    public function build_logs_where(array $filters, array &$values): string {
        global $wpdb;

        $where = ['1=1'];

        if (!empty($filters['date_from_sql'])) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['date_from_sql'];
        }

        if (!empty($filters['date_to_sql'])) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['date_to_sql'];
        }

        if (!empty($filters['component'])) {
            $selected_components = (array) $filters['component'];
            $placeholders = implode(', ', array_fill(0, count($selected_components), '%s'));
            $where[] = "component IN ({$placeholders})";
            foreach ($selected_components as $component) {
                $values[] = $component;
            }
        }

        if (!empty($filters['level'])) {
            $selected_levels = (array) $filters['level'];
            $placeholders = implode(', ', array_fill(0, count($selected_levels), '%s'));
            $where[] = "level IN ({$placeholders})";
            foreach ($selected_levels as $level) {
                $values[] = $level;
            }
        }

        if (!empty($filters['sol_id'])) {
            $where[] = 'sol_id LIKE %s';
            $values[] = '%' . $wpdb->esc_like((string) $filters['sol_id']) . '%';
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = (int) $filters['user_id'];
        }

        if (!empty($filters['search'])) {
            $search = (string) $filters['search'];
            $search_like = '%' . $wpdb->esc_like($search) . '%';

            $where[] = 'message LIKE %s';
            $values[] = $search_like;
        }

        return implode(' AND ', $where);
    }
}