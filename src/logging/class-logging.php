<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.4.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . '../../src/utilities/class-logger.php';
require_once __DIR__ . '/class-loggingsettings.php';
require_once __DIR__ . '/class-loggingfilters.php';
require_once __DIR__ . '/class-loggingdownload.php';
require_once __DIR__ . '/class-logginghelp.php';

use ScoutingOIDC\Logger;

/**
 * Provides the admin logging interface.
 *
 * @since 2.4.0
 */
class Logging {

	/**
	 * Hook suffix for the logging screen.
	 *
	 * @since 2.4.0
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Logging screen settings helper.
	 *
	 * @since 2.4.0
	 * @var LoggingSettings
	 */
	private LoggingSettings $settings;

	/**
	 * Logging filter parsing/query helper.
	 *
	 * @since 2.4.0
	 * @var LoggingFilters
	 */
	private LoggingFilters $filters_helper;

	/**
	 * Logging download helper.
	 *
	 * @since 2.4.0
	 * @var LoggingDownload
	 */
	private LoggingDownload $download_helper;

	/**
	 * Logging help tabs helper.
	 *
	 * @since 2.4.0
	 * @var LoggingHelp
	 */
	private LoggingHelp $help_helper;

	/**
	 * Initializes the logging page.
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		$this->settings        = new LoggingSettings();
		$this->filters_helper  = new LoggingFilters();
		$this->download_helper = new LoggingDownload();
		$this->help_helper     = new LoggingHelp();
		add_action( 'admin_post_scouting_oidc_download_logs', array( $this, 'handle_logs_download' ) );
	}

	/**
	 * Handles log download via admin-post endpoint.
	 *
	 * @since 2.4.0
	 */
	public function handle_logs_download(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to export logs.', 'scouting-openid-connect' ) );
		}

		$filters = $this->filters_helper->get_filters();
		$this->download_helper->download( $this, $filters );
	}

	/**
	 * Registers the logging page in the admin menu.
	 *
	 * @since 2.4.0
	 */
	public function scouting_oidc_logging_submenu_page(): void {
		$hook = add_submenu_page(
			'scouting-oidc-settings',
			'Logging',
			'Logging',
			'manage_options',
			'scouting-oidc-logging',
			array( $this, 'scouting_oidc_logging_page_callback' ),
			4
		);

		if ( is_string( $hook ) && '' !== $hook ) {
			$this->hook_suffix = $hook;
			add_action( "load-$hook", array( $this->settings, 'scouting_oidc_logs_register_screen_options' ) );
			add_action( "load-$hook", array( $this->help_helper, 'scouting_oidc_logging_register_help_tabs' ) );
			add_filter( "manage_{$hook}_columns", array( $this->settings, 'scouting_oidc_logs_register_screen_columns' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_logging_styles_and_scripts' ) );
		}
	}

	/**
	 * Enqueues logging page specific admin styles and scripts.
	 *
	 * @since 2.4.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_logging_styles_and_scripts( string $hook ): void {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'scouting-oidc-logging',
			plugins_url( 'logging.css', __FILE__ ),
			array(),
			SCOUTING_OIDC_VERSION,
		);

		// Enqueue the external JavaScript file.
		wp_enqueue_script(
			'logging-script',
			plugins_url( 'logging.js', __FILE__ ),
			array(),
			SCOUTING_OIDC_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Renders logging page content.
	 *
	 * @since 2.4.0
	 */
	public function scouting_oidc_logging_page_callback(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$filters = $this->filters_helper->get_filters();
		$sorting = $this->filters_helper->get_sorting();
		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$component_values = array_map( static fn( LogComponent $component ) => $component->value, LogComponent::cases() );
		$level_values     = array_map( static fn( LogLevel $level ) => $level->value, LogLevel::cases() );

		$list_table = new class($this, $filters, $sorting, $component_values, $level_values) extends \WP_List_Table {
			/**
			 * The logging page.
			 *
			 * @since 2.4.0
			 * @var Logging
			 */
			private Logging $logging;
			/**
			 * The filters.
			 *
			 * @since 2.4.0
			 * @var array<string, mixed>
			 */
			private array $filters;
			/**
			 * The sorting.
			 *
			 * @since 2.4.0
			 * @var array<string, string>
			 */
			private array $sorting;
			/**
			 * The component values.
			 *
			 * @since 2.4.0
			 * @var array<int, string>
			 */
			private array $component_values;
			/**
			 * The level values.
			 *
			 * @since 2.4.0
			 * @var array<int, string>
			 */
			private array $level_values;

			/**
			 * Initializes the log table.
			 *
			 * @since 2.4.0
			 *
			 * @param Logging               $logging The logging page instance.
			 * @param array<string, mixed>  $filters The active filters.
			 * @param array<string, string> $sorting The active sorting configuration.
			 * @param array<int, string>    $component_values The available component values.
			 * @param array<int, string>    $level_values The available level values.
			 */
			public function __construct( Logging $logging, array $filters, array $sorting, array $component_values, array $level_values ) {
				parent::__construct(
					array(
						'singular' => 'log',
						'plural'   => 'logs',
						'ajax'     => false,
					)
				);

				$this->logging          = $logging;
				$this->filters          = $filters;
				$this->sorting          = $sorting;
				$this->component_values = $component_values;
				$this->level_values     = $level_values;
			}

			/**
			 * Defines available columns for the logs table.
			 *
			 * @since 2.4.0
			 *
			 * @return array<string, string>.
			 */
			public function get_columns(): array {
				return array(
					'created_at' => __( 'Date/Time', 'scouting-openid-connect' ),
					'level'      => __( 'Level', 'scouting-openid-connect' ),
					'component'  => __( 'Component', 'scouting-openid-connect' ),
					'user_id'    => __( 'User ID', 'scouting-openid-connect' ),
					'sol_id'     => __( 'SOL ID', 'scouting-openid-connect' ),
					'message'    => __( 'Message', 'scouting-openid-connect' ),
				);
			}

			/**
			 * Defines sortable columns for the logs table.
			 *
			 * @since 2.4.0
			 *
			 * @return array<string, array{0: string, 1: bool}>.
			 */
			protected function get_sortable_columns(): array {
				return array(
					'created_at' => array( 'id', false ),
				);
			}

			/**
			 * Prepares table rows and pagination arguments.
			 *
			 * @since 2.4.0
			 */
			public function prepare_items(): void {
				$per_page     = $this->get_items_per_page( 'scouting_oidc_logs_per_page', 20 );
				$current_page = max( 1, $this->get_pagenum() );
				$offset       = ( $current_page - 1 ) * $per_page;

				$total_items = $this->logging->get_logs_count( $this->filters );
				$this->items = $this->logging->get_logs( $this->filters, $this->sorting, $per_page, $offset );

				$this->_column_headers = array( $this->get_columns(), get_hidden_columns( $this->screen ), $this->get_sortable_columns() );
				$this->set_pagination_args(
					array(
						'total_items' => $total_items,
						'per_page'    => $per_page,
						'total_pages' => $per_page > 0 ? (int) ceil( $total_items / $per_page ) : 1,
					)
				);
			}

			/**
			 * Renders custom filter controls inside the built-in table navigation.
			 *
			 * @since 2.4.0
			 *
			 * @param string $which The requested view.
			 */
			protected function extra_tablenav( $which ): void {
				if ( ! in_array( $which, array( 'top', 'bottom' ), true ) ) {
					return;
				}

				$position         = 'bottom' === $which ? 'bottom' : 'top';
				$is_submit_source = 'top' === $which;
				?>
				<div class="alignleft actions">
					<label class="screen-reader-text" for="date_from_<?php echo esc_attr( $position ); ?>"><?php esc_html_e( 'Date/time from', 'scouting-openid-connect' ); ?></label>
					<input type="datetime-local" id="date_from_<?php echo esc_attr( $position ); ?>" data-sync-key="date_from" <?php echo $is_submit_source ? 'name="date_from"' : ''; ?> value="<?php echo esc_attr( $this->filters['date_from'] ); ?>" step="0.001" />

					<label class="screen-reader-text" for="date_to_<?php echo esc_attr( $position ); ?>"><?php esc_html_e( 'Date/time to', 'scouting-openid-connect' ); ?></label>
					<input type="datetime-local" id="date_to_<?php echo esc_attr( $position ); ?>" data-sync-key="date_to" <?php echo $is_submit_source ? 'name="date_to"' : ''; ?> value="<?php echo esc_attr( $this->filters['date_to'] ); ?>" min="<?php echo esc_attr( $this->filters['date_from'] ); ?>" step="0.001" />

					<label class="screen-reader-text" for="level_<?php echo esc_attr( $position ); ?>"><?php esc_html_e( 'Filter by level', 'scouting-openid-connect' ); ?></label>
					<select id="level_<?php echo esc_attr( $position ); ?>" data-sync-key="level" <?php echo $is_submit_source ? 'name="level[]"' : ''; ?> multiple size="1">
						<?php foreach ( $this->level_values as $level_value ) : ?>
							<option value="<?php echo esc_attr( $level_value ); ?>" <?php echo in_array( $level_value, $this->filters['level'], true ) ? 'selected' : ''; ?>>
								<?php echo esc_html( strtoupper( $level_value ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label class="screen-reader-text" for="component_<?php echo esc_attr( $position ); ?>"><?php esc_html_e( 'Filter by component', 'scouting-openid-connect' ); ?></label>
					<select id="component_<?php echo esc_attr( $position ); ?>" data-sync-key="component" <?php echo $is_submit_source ? 'name="component[]"' : ''; ?> multiple size="1">
						<?php foreach ( $this->component_values as $component_value ) : ?>
							<option value="<?php echo esc_attr( $component_value ); ?>" <?php echo in_array( $component_value, $this->filters['component'], true ) ? 'selected' : ''; ?>>
								<?php echo esc_html( strtoupper( $component_value ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>

					<label class="screen-reader-text" for="user_id_<?php echo esc_attr( $position ); ?>"><?php esc_html_e( 'Filter by user ID', 'scouting-openid-connect' ); ?></label>
					<input type="number" min="1" step="1" id="user_id_<?php echo esc_attr( $position ); ?>" data-sync-key="user_id" <?php echo $is_submit_source ? 'name="user_id"' : ''; ?> value="<?php echo ! empty( $this->filters['user_id'] ) ? esc_attr( (string) $this->filters['user_id'] ) : ''; ?>" placeholder="<?php esc_attr_e( 'User ID', 'scouting-openid-connect' ); ?>" class="small-text" />

					<label class="screen-reader-text" for="sol_id_<?php echo esc_attr( $position ); ?>"><?php esc_html_e( 'Filter by SOL ID', 'scouting-openid-connect' ); ?></label>
					<input type="text" id="sol_id_<?php echo esc_attr( $position ); ?>" data-sync-key="sol_id" <?php echo $is_submit_source ? 'name="sol_id"' : ''; ?> value="<?php echo esc_attr( $this->filters['sol_id'] ); ?>" placeholder="<?php esc_attr_e( 'SOL ID', 'scouting-openid-connect' ); ?>" class="regular-text" />

					<input type="submit" id="post-query-submit-<?php echo esc_attr( $position ); ?>" class="button button-primary" value="<?php esc_attr_e( 'Filter', 'scouting-openid-connect' ); ?>" />
					<?php if ( $is_submit_source ) : ?>
						<button type="submit" class="button" name="action" value="scouting_oidc_download_logs" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php esc_html_e( 'Download .log', 'scouting-openid-connect' ); ?></button>
					<?php endif; ?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=scouting-oidc-logging&orderby=id&order=desc' ) ); ?>"><?php esc_html_e( 'Reset', 'scouting-openid-connect' ); ?></a>
				</div>
				<?php
			}

			/**
			 * Renders plain text columns by default.
			 *
			 * @since 2.4.0
			 *
			 * @param array<string, mixed> $item The log item.
			 * @param string               $column_name The column name.
			 * @return string String value.
			 */
			public function column_default( $item, $column_name ): string {
				// Custom rendering for specific columns.
				if ( 'created_at' === $column_name ) {
					// Display the stored UTC timestamp in the current site timezone.
					return esc_html( Logger::scouting_oidc_format_utc_datetime_for_site( (string) ( $item['created_at'] ?? '' ) ) );
				}

				if ( 'component' === $column_name || 'level' === $column_name ) {
					// Display component and level in uppercase for better readability.
					return esc_html( strtoupper( (string) ( $item[ $column_name ] ?? '—' ) ) );
				}

				if ( 'message' === $column_name ) {
					// Display the message column in a preformatted block to preserve formatting and allow line breaks.
					return '<pre style="margin:0; white-space:pre-wrap;">' . esc_html( (string) ( $item['message'] ?? '—' ) ) . '</pre>';
				}

				// Default rendering for other columns.
				return esc_html( (string) ( $item[ $column_name ] ?? '—' ) );
			}

			/**
			 * Renders the User ID column with links to user profiles when possible.
			 *
			 * @since 2.4.0
			 * @since 2.6.2 Links stored IDs only while their WordPress users exist.
			 *
			 * @param array<string, mixed> $item The log item.
			 * @return string String value.
			 */
			public function column_user_id( $item ): string {
				// The User ID column may contain a reference to a WordPress user. If it does, we attempt to link it to the corresponding user profile in WordPress for easier navigation.
				$user_id_value = isset( $item['user_id'] ) && null !== $item['user_id'] ? (int) $item['user_id'] : 0;
				if ( $user_id_value <= 0 ) {
					return '—';
				}

				// Link the stored ID only while the corresponding WordPress user exists.
				$user = get_userdata( $user_id_value );
				if ( false !== $user ) {
					$url = get_edit_user_link( $user_id_value );
					if ( is_string( $url ) && '' !== $url ) {
						return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( (string) $user_id_value ) . '</a>';
					}
				}

				// If no user is found with the given ID, just display the ID as plain text.
				return esc_html( (string) $user_id_value );
			}

			/**
			 * Renders the SOL ID column with links to user profiles when the SOL ID matches a user login.
			 *
			 * @since 2.4.0
			 *
			 * @param array<string, mixed> $item The log item.
			 * @return string String value.
			 */
			public function column_sol_id( $item ): string {
				// The SOL ID column may contain a user login. If it does, we attempt to link it to the corresponding user profile in WordPress for easier navigation.
				$sol_id_value = isset( $item['sol_id'] ) && null !== $item['sol_id'] ? trim( (string) $item['sol_id'] ) : '';
				if ( '' === $sol_id_value ) {
					return '—';
				}

				// Attempt to find a user with a matching login name and link to their profile if found.
				$user = get_user_by( 'login', $sol_id_value );
				if ( false !== $user && isset( $user->ID ) ) {
					$url = get_edit_user_link( (int) $user->ID );
					if ( is_string( $url ) && '' !== $url ) {
						return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $sol_id_value ) . '</a>';
					}
				}

				// If no user is found with the given SOL ID, just display the SOL ID as plain text.
				return esc_html( $sol_id_value );
			}

			/**
			 * Renders empty state row text.
			 *
			 * @since 2.4.0
			 */
			public function no_items(): void {
				esc_html_e( 'No log entries found for the selected filters.', 'scouting-openid-connect' );
			}
		};

		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Logging', 'scouting-openid-connect' ); ?></h1>

			<form id="scouting-oidc-logs-filter" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
				<input type="hidden" name="page" value="scouting-oidc-logging" />
				<input type="hidden" name="filter_applied" value="1" />
				<input type="hidden" name="orderby" value="<?php echo esc_attr( $sorting['orderby'] ); ?>" />
				<input type="hidden" name="order" value="<?php echo esc_attr( strtolower( $sorting['order'] ) ); ?>" />
				<?php wp_nonce_field( 'scouting_oidc_logs_filter', 'scouting_oidc_logs_filter_nonce', false ); ?>
				<?php $list_table->search_box( __( 'Search Logs', 'scouting-openid-connect' ), 'scouting-oidc-logs' ); ?>
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}


	/**
	 * Counts filtered logs for pagination.
	 *
	 * @since 2.4.0
	 *
	 * @param array<string, mixed> $filters The active filters.
	 * @return int Integer value.
	 */
	public function get_logs_count( array $filters ): int {
		global $wpdb;

		$values    = array();
		$where_sql = $this->filters_helper->build_logs_where( $filters, $values );

		$scouting_oidc_logs_table = esc_sql( $wpdb->prefix . 'scouting_oidc_logs' );
		$sql                      = "SELECT COUNT(*) FROM {$scouting_oidc_logs_table} WHERE {$where_sql}";

		if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared_sql = $wpdb->prepare( $sql, $values );
			if ( ! is_string( $prepared_sql ) || '' === $prepared_sql ) {
				return 0;
			}
			$sql = $prepared_sql;
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$count = $wpdb->get_var( $sql );

		return (int) $count;
	}

	/**
	 * Retrieves filtered logs from the database.
	 *
	 * @since 2.4.0
	 *
	 * @param array<string, mixed>  $filters The active filters.
	 * @param array<string, string> $sorting The active sorting configuration.
	 * @param int                   $limit Optional. The maximum number of entries. Default 999.
	 * @param int                   $offset Optional. The starting offset. Default 0.
	 * @return array<int, array<string, mixed>>.
	 */
	public function get_logs( array $filters, array $sorting, int $limit = 999, int $offset = 0 ): array {
		global $wpdb;

		$values    = array();
		$where_sql = $this->filters_helper->build_logs_where( $filters, $values );

		$order = ( isset( $sorting['order'] ) && 'asc' === $sorting['order'] ) ? 'ASC' : 'DESC';
		// Limit should be between 1 and 999.
		$limit = max( 1, min( 999, $limit ) );

		// Offset should be zero or positive.
		$offset = max( 0, $offset );

		$scouting_oidc_logs_table = esc_sql( $wpdb->prefix . 'scouting_oidc_logs' );
		$sql                      = "SELECT id, created_at, component, level, user_id, sol_id, message
                FROM {$scouting_oidc_logs_table}
                WHERE {$where_sql}
                ORDER BY id {$order}
                LIMIT {$limit} OFFSET {$offset}";

		if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared_sql = $wpdb->prepare( $sql, $values );
			if ( ! is_string( $prepared_sql ) || '' === $prepared_sql ) {
				return array();
			}
			$sql = $prepared_sql;
		}

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $results ) ? $results : array();
	}
}
