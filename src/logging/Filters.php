<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class LoggingFilters
{
    /**
     * Request key used for the logging filter nonce.
     */
    private const FILTER_NONCE_KEY = 'scouting_oidc_logs_filter_nonce';

    /**
     * Get sorting options from the request.
     *
     * @return array<string, string>
     */
    public function get_sorting(): array {
        $nonce = isset($_GET[self::FILTER_NONCE_KEY])
            ? sanitize_text_field(wp_unslash($_GET[self::FILTER_NONCE_KEY]))
            : (isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '');
        if ($nonce !== '' && !wp_verify_nonce($nonce, 'scouting_oidc_logs_filter')) {
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
     * Resolve an HTML datetime-local value to normalized and UTC SQL variants.
     *
     * @param string $value Raw datetime-local value.
     * @return array{normalized: string, utc_sql: string|null}
     */
    private function resolve_datetime_local(string $value): array {
        $trimmed_value = trim($value);
        if ($trimmed_value === '') {
            return [
                'normalized' => '',
                'utc_sql' => null,
            ];
        }

        $site_timezone = wp_timezone();
        $formats = ['Y-m-d\\TH:i:s.v', 'Y-m-d\\TH:i:s', 'Y-m-d\\TH:i'];

        foreach ($formats as $format) {
            $datetime = \DateTimeImmutable::createFromFormat($format, $trimmed_value, $site_timezone);
            if ($datetime !== false) {
                return [
                    'normalized' => $datetime->format('Y-m-d\\TH:i:s.v'),
                    'utc_sql' => $datetime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v'),
                ];
            }
        }

        return [
            'normalized' => $trimmed_value,
            'utc_sql' => null,
        ];
    }

    /**
     * Get and validate filter values from the request.
     *
     * @return array<string, mixed>
     */
    public function get_filters(): array {
        $component_values = array_map(static fn(LogComponent $case) => $case->value, LogComponent::cases());
        $level_values = array_map(static fn(LogLevel $case) => $case->value, LogLevel::cases());

        $nonce = isset($_GET[self::FILTER_NONCE_KEY])
            ? sanitize_text_field(wp_unslash($_GET[self::FILTER_NONCE_KEY]))
            : (isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '');
        if (!$nonce || !wp_verify_nonce($nonce, 'scouting_oidc_logs_filter')) {
            $default_levels = array_values(array_filter($level_values, fn($v) => $v !== 'debug'));

            return [
                'date_from' => '',
                'date_to' => '',
                'date_from_utc_sql' => null,
                'date_to_utc_sql' => null,
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
        $date_from_resolved = $this->resolve_datetime_local($date_from);
        $date_to_resolved = $this->resolve_datetime_local($date_to);

        return [
            'date_from' => $date_from_resolved['normalized'],
            'date_to' => $date_to_resolved['normalized'],
            'date_from_utc_sql' => $date_from_resolved['utc_sql'],
            'date_to_utc_sql' => $date_to_resolved['utc_sql'],
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

        if (!empty($filters['date_from_utc_sql'])) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['date_from_utc_sql'];
        }

        if (!empty($filters['date_to_utc_sql'])) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['date_to_utc_sql'];
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