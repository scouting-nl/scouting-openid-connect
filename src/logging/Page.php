<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . '../../src/utilities/Logger.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Filters.php';

use ScoutingOIDC\Logger;

class Logging
{
    /**
     * Hook suffix for the logging screen.
     *
     * @var string
     */
    private string $hook_suffix = '';

    /**
     * Logging screen settings helper.
     *
     * @var LoggingSettings
     */
    private LoggingSettings $settings;

    /**
     * Logging filter parsing/query helper.
     *
     * @var LoggingFilters
     */
    private LoggingFilters $filters_helper;

    /**
     * @return void
     */
    public function __construct() {
        $this->settings = new LoggingSettings();
        $this->filters_helper = new LoggingFilters();
    }

    /** Register the logging page in the admin menu
     * 
     * @return void
     */
    public function scouting_oidc_logging_submenu_page(): void {
        $hook = add_submenu_page(
            'scouting-oidc-settings',                       // Parent slug (matches the main menu slug)
            'Logging',                                      // Page title
            'Logging',                                      // Menu title
            'manage_options',                               // Capability
            'scouting-oidc-logging',                        // Submenu slug
            [$this, 'scouting_oidc_logging_page_callback'], // Callback function
            4                                               // Menu position
        );

        if (is_string($hook) && $hook !== '') {
            $this->hook_suffix = $hook;
            add_action("load-$hook", [$this->settings, 'scouting_oidc_logs_register_screen_options']);
            add_filter("manage_{$hook}_columns", [$this->settings, 'scouting_oidc_logs_register_screen_columns']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_logging_styles_and_scripts']);
        }
    }

    /**
     * Enqueue logging page specific admin styles and scripts.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_logging_styles_and_scripts(string $hook): void {
        if ($hook !== $this->hook_suffix) {
            return;
        }

        wp_enqueue_style(
            'scouting-oidc-logging',
            plugins_url('logging.css', __FILE__), // Path to the file
            array(),                              // No dependencies
            "2.3.0",                              // Version number
        );

        // Enqueue the external JavaScript file
        wp_enqueue_script(
            'logging-script',                    // Handle name
            plugins_url('logging.js', __FILE__), // Path to the file
            array(),                             // No dependencies
            "2.3.0",                             // Version number
            array(
                'strategy' => 'defer',           // Add the defer attribute
                'in_footer' => true              // Load the script in the footer
            )
        );
    }

    /** Callback to render logging page content
     * 
     * @return void
     */
    public function scouting_oidc_logging_page_callback(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $filters = $this->filters_helper->get_filters();
        $sorting = $this->filters_helper->get_sorting();
        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $type_values = array_map(static fn(LogType $case) => $case->value, LogType::cases());
        $level_values = array_map(static fn(LogLevel $case) => $case->value, LogLevel::cases());

        $list_table = new class($this, $filters, $sorting, $type_values, $level_values) extends \WP_List_Table {
            private Logging $logging;
            /** @var array<string, mixed> */
            private array $filters;
            /** @var array<string, string> */
            private array $sorting;
            /** @var array<int, string> */
            private array $type_values;
            /** @var array<int, string> */
            private array $level_values;

            /**
             * @param Logging $logging
             * @param array<string, mixed> $filters
             * @param array<string, string> $sorting
             * @param array<int, string> $type_values
             * @param array<int, string> $level_values
             */
            public function __construct(Logging $logging, array $filters, array $sorting, array $type_values, array $level_values) {
                parent::__construct([
                    'singular' => 'log',
                    'plural' => 'logs',
                    'ajax' => false,
                ]);

                $this->logging = $logging;
                $this->filters = $filters;
                $this->sorting = $sorting;
                $this->type_values = $type_values;
                $this->level_values = $level_values;
            }

            /**
             * Define available columns for the logs table.
             * 
             * @return array<string, string>
             */
            public function get_columns(): array {
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
             * Define sortable columns for the logs table.
             * 
             * @return array<string, array{0: string, 1: bool}>
             */
            protected function get_sortable_columns(): array {
                return [
                    'created_at' => ['created_at', true],
                ];
            }

            /**
             * Prepare table rows and pagination arguments.
             *
             * @return void
             */
            public function prepare_items(): void {
                $per_page = $this->get_items_per_page('scouting_oidc_logs_per_page', 20);
                $current_page = max(1, $this->get_pagenum());
                $offset = ($current_page - 1) * $per_page;

                $total_items = $this->logging->get_logs_count($this->filters);
                $this->items = $this->logging->get_logs($this->filters, $this->sorting, $per_page, $offset);

                $this->_column_headers = [$this->get_columns(), get_hidden_columns($this->screen), $this->get_sortable_columns()];
                $this->set_pagination_args([
                    'total_items' => $total_items,
                    'per_page' => $per_page,
                    'total_pages' => $per_page > 0 ? (int) ceil($total_items / $per_page) : 1,
                ]);
            }

            /**
             * Render custom filter controls inside the built-in table navigation.
             *
             * @param string $which
             * @return void
             */
            protected function extra_tablenav($which): void {
                if ($which !== 'top') {
                    return;
                }
                ?>
                <div class="alignleft actions">
                    <label class="screen-reader-text" for="date_from"><?php esc_html_e('Date/time from', 'scouting-openid-connect'); ?></label>
                    <input type="datetime-local" id="date_from" name="date_from" value="<?php echo esc_attr($this->filters['date_from']); ?>" step="0.001" />

                    <label class="screen-reader-text" for="date_to"><?php esc_html_e('Date/time to', 'scouting-openid-connect'); ?></label>
                    <input type="datetime-local" id="date_to" name="date_to" value="<?php echo esc_attr($this->filters['date_to']); ?>" min="<?php echo esc_attr($this->filters['date_from']); ?>" step="0.001" />

                    <label class="screen-reader-text" for="level"><?php esc_html_e('Filter by level', 'scouting-openid-connect'); ?></label>
                    <select id="level" name="level[]" multiple size="1">
                        <?php foreach ($this->level_values as $level_value): ?>
                            <option value="<?php echo esc_attr($level_value); ?>" <?php echo in_array($level_value, $this->filters['level'], true) ? 'selected' : ''; ?>>
                                <?php echo esc_html(strtoupper($level_value)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label class="screen-reader-text" for="type"><?php esc_html_e('Filter by type', 'scouting-openid-connect'); ?></label>
                    <select id="type" name="type[]" multiple size="1">
                        <?php foreach ($this->type_values as $type_value): ?>
                            <option value="<?php echo esc_attr($type_value); ?>" <?php echo in_array($type_value, $this->filters['type'], true) ? 'selected' : ''; ?>>
                                <?php echo esc_html(strtoupper($type_value)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label class="screen-reader-text" for="user_id"><?php esc_html_e('Filter by user ID', 'scouting-openid-connect'); ?></label>
                    <input type="number" min="1" step="1" id="user_id" name="user_id" value="<?php echo !empty($this->filters['user_id']) ? esc_attr((string) $this->filters['user_id']) : ''; ?>" placeholder="<?php esc_attr_e('User ID', 'scouting-openid-connect'); ?>" class="small-text" />

                    <label class="screen-reader-text" for="sol_id"><?php esc_html_e('Filter by SOL ID', 'scouting-openid-connect'); ?></label>
                    <input type="text" id="sol_id" name="sol_id" value="<?php echo esc_attr($this->filters['sol_id']); ?>" placeholder="<?php esc_attr_e('SOL ID', 'scouting-openid-connect'); ?>" class="regular-text" />

                    <input type="submit" id="post-query-submit" class="button button-primary" value="<?php esc_attr_e('Filter', 'scouting-openid-connect'); ?>" />
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=scouting-oidc-logging&orderby=created_at&order=desc')); ?>"><?php esc_html_e('Reset', 'scouting-openid-connect'); ?></a>
                </div>
                <?php
            }

            /**
             * Default rendering for plain text columns.
             *
             * @param array<string, mixed> $item
             * @param string $column_name
             * @return string
             */
            public function column_default($item, $column_name): string {
                // Custom rendering for specific columns
                if ($column_name === 'created_at') {
                    // Display the created_at column with milliseconds, formatted as "dd-mm-yyyy hh:mm:ss.fff"
                    return esc_html(substr((string) ($item['created_at_with_ms'] ?? ''), 0, 23));
                }

                if ($column_name === 'type' || $column_name === 'level') {
                    // Display type and level in uppercase for better readability
                    return esc_html(strtoupper((string) ($item[$column_name] ?? '—')));
                }

                if ($column_name === 'message') {
                    // Display the message column in a preformatted block to preserve formatting and allow line breaks
                    return '<pre style="margin:0; white-space:pre-wrap;">' . esc_html((string) ($item['message'] ?? '—')) . '</pre>';
                }

                // Default rendering for other columns
                return esc_html((string) ($item[$column_name] ?? '—'));
            }

            /**
             * @param array<string, mixed> $item
             * @return string
             */
            public function column_user_id($item): string {
                $user_id_value = isset($item['user_id']) && $item['user_id'] !== null ? (int) $item['user_id'] : 0;
                if ($user_id_value <= 0) {
                    return '—';
                }

                $url = get_edit_user_link($user_id_value);
                if (is_string($url) && $url !== '') {
                    return '<a href="' . esc_url($url) . '">' . esc_html((string) $user_id_value) . '</a>';
                }

                return esc_html((string) $user_id_value);
            }

            /**
             * @param array<string, mixed> $item
             * @return string
             */
            public function column_sol_id($item): string {
                $sol_id_value = isset($item['sol_id']) && $item['sol_id'] !== null ? trim((string) $item['sol_id']) : '';
                if ($sol_id_value === '') {
                    return '—';
                }

                $user = get_user_by('login', $sol_id_value);
                if ($user !== false && isset($user->ID)) {
                    $url = get_edit_user_link((int) $user->ID);
                    if (is_string($url) && $url !== '') {
                        return '<a href="' . esc_url($url) . '">' . esc_html($sol_id_value) . '</a>';
                    }
                }

                return esc_html($sol_id_value);
            }

            /**
             * Render empty state row text.
             *
             * @return void
             */
            public function no_items(): void {
                esc_html_e('No log entries found for the selected filters.', 'scouting-openid-connect');
            }
        };

        $list_table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Logging', 'scouting-openid-connect'); ?></h1>

            <form id="scouting-oidc-logs-filter" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="scouting-oidc-logging" />
                <input type="hidden" name="filter_applied" value="1" />
                <input type="hidden" name="orderby" value="<?php echo esc_attr($sorting['orderby']); ?>" />
                <input type="hidden" name="order" value="<?php echo esc_attr(strtolower($sorting['order'])); ?>" />
                <?php $list_table->display(); ?>
            </form>
        </div>
        <?php
    }


    /**
     * Count filtered logs for pagination.
     *
     * @param array<string, mixed> $filters
     * @return int
     */
    public function get_logs_count(array $filters): int {
        global $wpdb;

        $values = [];
        $where_sql = $this->filters_helper->build_logs_where($filters, $values);

        $scouting_oidc_logs_table = esc_sql($wpdb->prefix . 'scouting_oidc_logs');
        $sql = "SELECT COUNT(*) FROM {$scouting_oidc_logs_table} WHERE {$where_sql}";

        if (!empty($values)) {
            $prepared_sql = $wpdb->prepare($sql, $values);
            if (!is_string($prepared_sql) || $prepared_sql === '') {
                return 0;
            }
            $sql = $prepared_sql;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var($sql);

        return (int) $count;
    }

    /**
     * Retrieve filtered logs from the database.
     *
     * @param array<string, mixed> $filters
     * @param array<string, string> $sorting
     * @param int $limit
     * @param int $offset
     * @return array<int, array<string, mixed>>
     */
    public function get_logs(array $filters, array $sorting, int $limit = 999, int $offset = 0): array {
        global $wpdb;

        $values = [];
        $where_sql = $this->filters_helper->build_logs_where($filters, $values);

        $allowed_orderby = ['id', 'created_at'];
        $orderby = isset($sorting['orderby']) && in_array($sorting['orderby'], $allowed_orderby, true)
            ? $sorting['orderby']
            : 'created_at';
        $order = (isset($sorting['order']) && $sorting['order'] === 'ASC') ? 'ASC' : 'DESC';

        // Limit should be between 1 and 999
        $limit = max(1, min(999, $limit));

        // Offset should be zero or positive
        $offset = max(0, $offset);

        $scouting_oidc_logs_table = esc_sql($wpdb->prefix . 'scouting_oidc_logs');
        $order_by_sql = $orderby === 'created_at'
            ? "created_at {$order}, id {$order}"
            : "id {$order}";
        $sql = "SELECT id, created_at, DATE_FORMAT(created_at, '%%d-%%m-%%Y %%H:%%i:%%s.%%f') AS created_at_with_ms, type, level, user_id, sol_id, message
                FROM {$scouting_oidc_logs_table}
                WHERE {$where_sql}
                ORDER BY {$order_by_sql}
                LIMIT {$limit} OFFSET {$offset}";

        if (!empty($values)) {
            $prepared_sql = $wpdb->prepare($sql, $values);
            if (!is_string($prepared_sql) || $prepared_sql === '') {
                return [];
            }
            $sql = $prepared_sql;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $results = $wpdb->get_results($sql, ARRAY_A);

        return is_array($results) ? $results : [];
    }
}