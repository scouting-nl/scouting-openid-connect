<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.7.0 Adds SOL role explorer.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays synchronized SOL roles and their user assignments.
 *
 * @since 2.7.0 Adds SOL role explorer.
 */
class RolesExplorer {
	/**
	 * Assignments displayed per page.
	 *
	 * @since 2.7.0 Paginates SOL role assignments.
	 */
	private const PAGE_SIZE = 50;

	/**
	 * Renders the lazy organisation tree and filtered assignments.
	 *
	 * @since Unreleased Adds lazy organisation, unit, and role filters.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view SOL assignments.', 'scouting-openid-connect' ) );
		}
		if ( ! RolesAuthorization::scouting_oidc_roles_authorization_has_roles_scope() ) {
			RolesAdmin::scouting_oidc_roles_admin_render_scope_notice();
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- These query parameters only filter read-only administrator data.
		$filters = array(
			'organisation_id'      => isset( $_GET['organisation_id'] ) ? sanitize_text_field( wp_unslash( $_GET['organisation_id'] ) ) : '',
			'organisation_unit_id' => isset( $_GET['organisation_unit_id'] ) ? absint( $_GET['organisation_unit_id'] ) : 0,
			'role_id'              => isset( $_GET['role_id'] ) ? absint( $_GET['role_id'] ) : 0,
		);
		$page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$data            = '' === $filters['organisation_id'] ? array(
			'rows'  => array(),
			'total' => 0,
		) : $this->get_assignments( $filters, $page );
		$assignment_rows = is_wp_error( $data ) ? array() : $data['rows'];
		$organisations   = $this->get_root_organisations();
		$expanded_nodes  = $this->get_selected_tree_branches( $filters );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SOL Role Explorer', 'scouting-openid-connect' ); ?></h1>
			<?php RolesAdmin::scouting_oidc_roles_admin_render_scope_notice(); ?>
			<p><?php esc_html_e( 'Expand an organisation to load its child organisations and units. Expand a unit to load its roles. Selecting an organisation includes assignments from its child organisations. Select any item to filter assignments.', 'scouting-openid-connect' ); ?></p>
			<div class="scouting-oidc-explorer-layout">
				<section class="scouting-oidc-explorer-tree-panel" aria-labelledby="sol-explorer-tree-heading">
					<h2 id="sol-explorer-tree-heading"><?php esc_html_e( 'Organisations, units and roles', 'scouting-openid-connect' ); ?></h2>
					<p class="scouting-oidc-explorer-panel-meta">
						<?php if ( '' !== $filters['organisation_id'] ) : ?>
							<strong><?php esc_html_e( 'Current filter:', 'scouting-openid-connect' ); ?></strong>
							<?php
							printf(
								/* translators: %s: six-digit Scouts Online organisation ID. */
								esc_html__( 'Organisation %s', 'scouting-openid-connect' ),
								esc_html( $filters['organisation_id'] )
							);
							if ( 0 !== $filters['organisation_unit_id'] ) :
								printf(
									/* translators: %s: Scouts Online organisation unit ID. */
									esc_html__( ' / Unit %s', 'scouting-openid-connect' ),
									esc_html( (string) $filters['organisation_unit_id'] )
								);
							endif;
							if ( 0 !== $filters['role_id'] ) :
								printf(
									/* translators: %s: Scouts Online role ID. */
									esc_html__( ' / Role %s', 'scouting-openid-connect' ),
									esc_html( (string) $filters['role_id'] )
								);
							endif;
							?>
						<?php endif; ?>
					</p>
					<?php if ( is_wp_error( $organisations ) || is_wp_error( $expanded_nodes ) ) : ?>
						<p class="notice notice-error"><?php esc_html_e( 'The organisation tree could not be loaded. Check the database and try again.', 'scouting-openid-connect' ); ?></p>
					<?php elseif ( empty( $organisations ) ) : ?>
						<p><?php esc_html_e( 'No organisations have been synchronized yet.', 'scouting-openid-connect' ); ?></p>
					<?php else : ?>
						<ul class="scouting-oidc-explorer-tree" id="sol-explorer-tree" data-expanded-nodes="<?php echo esc_attr( wp_json_encode( $expanded_nodes ) ); ?>" aria-label="<?php esc_attr_e( 'Organisation hierarchy', 'scouting-openid-connect' ); ?>">
							<?php foreach ( $organisations as $organisation ) : ?>
								<?php $this->render_organisation_node( $organisation, $filters ); ?>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="scouting-oidc-explorer-status" id="sol-explorer-status" role="status" aria-live="polite" aria-atomic="true"></p>
				</section>
				<section class="scouting-oidc-explorer-results" aria-labelledby="sol-explorer-results-heading">
					<h2 id="sol-explorer-results-heading"><?php esc_html_e( 'Role assignments', 'scouting-openid-connect' ); ?></h2>
					<p class="scouting-oidc-explorer-panel-meta">
						<?php if ( '' !== $filters['organisation_id'] && ! is_wp_error( $data ) ) : ?>
							<?php
							/* translators: %s: number of matching SOL role assignments. */
							echo esc_html( sprintf( _n( '%s assignment found.', '%s assignments found.', $data['total'], 'scouting-openid-connect' ), number_format_i18n( $data['total'] ) ) );
							?>
						<?php endif; ?>
					</p>
					<table class="widefat striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Organisation', 'scouting-openid-connect' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Organisation Unit', 'scouting-openid-connect' ); ?></th>
								<th scope="col"><?php esc_html_e( 'SOL Role', 'scouting-openid-connect' ); ?></th>
								<th scope="col"><?php esc_html_e( 'User', 'scouting-openid-connect' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Starts', 'scouting-openid-connect' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Ends', 'scouting-openid-connect' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php if ( '' === $filters['organisation_id'] ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'Select a parent organisation, organisation, unit or role to view assignments.', 'scouting-openid-connect' ); ?></td></tr>
						<?php elseif ( is_wp_error( $data ) ) : ?>
							<tr><td colspan="6"><div class="notice notice-error" role="alert"><p><?php esc_html_e( 'The assignments could not be loaded. Check the database and try again.', 'scouting-openid-connect' ); ?></p></div></td></tr>
						<?php elseif ( empty( $data['rows'] ) ) : ?>
							<tr><td colspan="6"><?php esc_html_e( 'No role assignments match this filter.', 'scouting-openid-connect' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $assignment_rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['organisation_name'] . ' (' . $row['organisation_id'] . ')' ); ?></td>
								<td><?php echo esc_html( $this->format_unit_name( $row ) . ' (' . $row['organisation_unit_id'] . ')' ); ?></td>
								<td><?php echo esc_html( $row['role_name'] . ' (' . $row['role_id'] . ')' ); ?></td>
								<td><a href="<?php echo esc_url( get_edit_user_link( (int) $row['user_id'] ) ); ?>"><?php echo esc_html( $row['display_name'] . ' (' . $row['user_login'] . ')' ); ?></a></td>
								<td><?php echo esc_html( $this->format_assignment_date( $row['start_date'] ) ); ?></td>
								<td><?php echo null === $row['end_date'] ? esc_html__( 'No end date', 'scouting-openid-connect' ) : esc_html( $this->format_assignment_date( $row['end_date'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( ! is_wp_error( $data ) ) : ?>
						<?php $this->render_pagination( $filters, $page, $data['total'] ); ?>
					<?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}

	/**
	 * Loads the children and units of an organisation, or roles in a unit.
	 *
	 * @since Unreleased Loads SOL Explorer tree branches on demand.
	 */
	public static function ajax_get_tree_children(): void {
		check_ajax_referer( 'scouting_oidc_roles_explorer_tree' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to browse SOL assignments.', 'scouting-openid-connect' ) ), 403 );
		}
		if ( ! RolesAuthorization::scouting_oidc_roles_authorization_has_roles_scope() ) {
			wp_send_json_error( array( 'message' => __( 'The SOL roles scope is not enabled.', 'scouting-openid-connect' ) ), 403 );
		}

		$node_type = isset( $_POST['node_type'] ) ? sanitize_key( wp_unslash( $_POST['node_type'] ) ) : '';
		$node_id   = isset( $_POST['node_id'] ) ? sanitize_text_field( wp_unslash( $_POST['node_id'] ) ) : '';
		$explorer  = new self();
		$nodes     = $explorer->get_tree_children( $node_type, $node_id );
		if ( is_wp_error( $nodes ) ) {
			wp_send_json_error( array( 'message' => __( 'The selected tree item could not be loaded. Check the database and try again.', 'scouting-openid-connect' ) ), 500 );
		}

		wp_send_json_success( $nodes );
	}

	/**
	 * Finds only the ancestor branches needed to reveal the selected filter.
	 *
	 * @since Unreleased Restores the selected tree path on navigation.
	 *
	 * @param array $filters Current assignment filters.
	 * @return array<int, string>|\WP_Error Branch keys or a database error.
	 */
	private function get_selected_tree_branches( array $filters ): array|\WP_Error {
		if ( '' === $filters['organisation_id'] ) {
			return array();
		}

		global $wpdb;

		$table    = $wpdb->prefix . 'scouting_oidc_sol_organisation_parents';
		$frontier = array( $filters['organisation_id'] );
		$known    = array( $filters['organisation_id'] => true );
		$branches = array( 'organisation:' . $filters['organisation_id'] );

		while ( ! empty( $frontier ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$parents = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT parent_organisation_id FROM %i WHERE child_organisation_id IN (' . implode( ', ', array_fill( 0, count( $frontier ), '%s' ) ) . ')',
					array_merge( array( $table ), $frontier )
				)
			);
			if ( ! is_array( $parents ) || '' !== $wpdb->last_error ) {
				Logger::error( LogComponent::ROLES, 'Could not load the selected SOL Explorer ancestor path.' );
				return new \WP_Error( 'sol_explorer_tree_read_failed' );
			}

			$frontier = array();
			foreach ( $parents as $parent_id ) {
				if ( isset( $known[ $parent_id ] ) ) {
					continue;
				}
				$known[ $parent_id ] = true;
				$frontier[]          = $parent_id;
				$branches[]          = 'organisation:' . $parent_id;
			}
		}

		if ( 0 !== $filters['organisation_unit_id'] ) {
			$branches[] = 'unit:' . $filters['organisation_unit_id'];
		}

		return $branches;
	}

	/**
	 * Gets top-level organisations only.
	 *
	 * @since Unreleased Loads only top-level SOL organisations initially.
	 *
	 * @return array<int, array{organisation_id: string, name: string}>|\WP_Error Organisations or a database error.
	 */
	private function get_root_organisations(): array|\WP_Error {
		global $wpdb;
		$organisations_table = $wpdb->prefix . 'scouting_oidc_sol_organisations';
		$parents_table       = $wpdb->prefix . 'scouting_oidc_sol_organisation_parents';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT organisation.organisation_id, organisation.name FROM %i AS organisation ' .
				'LEFT JOIN %i AS parent ON parent.child_organisation_id = organisation.organisation_id ' .
				'WHERE parent.child_organisation_id IS NULL ORDER BY organisation.name, organisation.organisation_id',
				$organisations_table,
				$parents_table
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			Logger::error( LogComponent::ROLES, 'Could not load top-level SOL organisations for the role explorer.' );
			return new \WP_Error( 'sol_explorer_tree_read_failed' );
		}

		return $rows;
	}

	/**
	 * Loads a single level of an organisation tree.
	 *
	 * @since Unreleased Loads SOL Explorer branches only when expanded.
	 *
	 * @param string $node_type Tree node type.
	 * @param string $node_id Tree node identifier.
	 * @return array<int, array{type: string, id: string, name: string}>|\WP_Error Child nodes or a database error.
	 */
	private function get_tree_children( string $node_type, string $node_id ): array|\WP_Error {
		global $wpdb;

		if ( 'organisation' === $node_type && 1 !== preg_match( '/^\d{6}$/', $node_id ) ) {
			return new \WP_Error( 'invalid_organisation_id' );
		}
		if ( 'unit' === $node_type && ( 1 !== preg_match( '/^[1-9]\d*$/', $node_id ) || false === filter_var( $node_id, FILTER_VALIDATE_INT ) ) ) {
			return new \WP_Error( 'invalid_organisation_unit_id' );
		}
		if ( ! in_array( $node_type, array( 'organisation', 'unit' ), true ) ) {
			return new \WP_Error( 'invalid_tree_node_type' );
		}

		if ( 'unit' === $node_type ) {
			$roles_table = $wpdb->prefix . 'scouting_oidc_sol_roles';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT role_id, role_name FROM %i WHERE organisation_unit_id = %d ORDER BY role_name, role_id',
					$roles_table,
					(int) $node_id
				),
				ARRAY_A
			);
			if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
				Logger::error( LogComponent::ROLES, 'Could not load SOL roles for an organisation unit in the role explorer.' );
				return new \WP_Error( 'sol_explorer_tree_read_failed' );
			}

			$nodes = array();
			foreach ( $rows as $row ) {
				$nodes[] = array(
					'type' => 'role',
					'id'   => (string) $row['role_id'],
					'name' => $row['role_name'],
				);
			}
			return $nodes;
		}

		$parents_table       = $wpdb->prefix . 'scouting_oidc_sol_organisation_parents';
		$organisations_table = $wpdb->prefix . 'scouting_oidc_sol_organisations';
		$units_table         = $wpdb->prefix . 'scouting_oidc_sol_organisation_units';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$organisation_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT organisation.organisation_id, organisation.name FROM %i AS parent ' .
				'INNER JOIN %i AS organisation ON organisation.organisation_id = parent.child_organisation_id ' .
				'WHERE parent.parent_organisation_id = %s ORDER BY organisation.name, organisation.organisation_id',
				$parents_table,
				$organisations_table,
				$node_id
			),
			ARRAY_A
		);
		if ( ! is_array( $organisation_rows ) || '' !== $wpdb->last_error ) {
			Logger::error( LogComponent::ROLES, 'Could not load child SOL organisations in the role explorer.' );
			return new \WP_Error( 'sol_explorer_tree_read_failed' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$unit_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT organisation_unit_id, name, category, nature FROM %i WHERE organisation_id = %s ORDER BY name, organisation_unit_id',
				$units_table,
				$node_id
			),
			ARRAY_A
		);
		if ( ! is_array( $unit_rows ) || '' !== $wpdb->last_error ) {
			Logger::error( LogComponent::ROLES, 'Could not load SOL organisation units in the role explorer.' );
			return new \WP_Error( 'sol_explorer_tree_read_failed' );
		}

		$nodes = array();
		foreach ( $organisation_rows as $row ) {
			$nodes[] = array(
				'type' => 'organisation',
				'id'   => (string) $row['organisation_id'],
				'name' => $row['name'],
			);
		}
		foreach ( $unit_rows as $row ) {
			$nodes[] = array(
				'type' => 'unit',
				'id'   => (string) $row['organisation_unit_id'],
				'name' => $this->format_unit_name( $row ),
			);
		}

		return $nodes;
	}

	/**
	 * Renders one expandable organisation tree node.
	 *
	 * @since Unreleased Adds accessible lazy organisation tree controls.
	 *
	 * @param array $organisation Organisation row.
	 * @param array $filters Current assignments filters.
	 */
	private function render_organisation_node( array $organisation, array $filters ): void {
		$organisation_id = (string) $organisation['organisation_id'];
		$children_id     = 'sol-explorer-organisation-' . $organisation_id;
		$is_selected     = $filters['organisation_id'] === $organisation_id && 0 === $filters['organisation_unit_id'] && 0 === $filters['role_id'];
		$url             = add_query_arg(
			array(
				'page'            => 'scouting-oidc-role-explorer',
				'organisation_id' => $organisation_id,
			),
			admin_url( 'admin.php' )
		);
		/* translators: %s: organisation name. */
		$expand_label = sprintf( __( 'Expand %s', 'scouting-openid-connect' ), $organisation['name'] );
		?>
		<li class="scouting-oidc-explorer-node scouting-oidc-explorer-organisation">
			<button class="scouting-oidc-explorer-toggle" type="button" data-node-type="organisation" data-node-id="<?php echo esc_attr( $organisation_id ); ?>" data-organisation-id="<?php echo esc_attr( $organisation_id ); ?>" aria-expanded="false" aria-controls="<?php echo esc_attr( $children_id ); ?>" aria-label="<?php echo esc_attr( $expand_label ); ?>">+</button>
			<a href="<?php echo esc_url( $url ); ?>" <?php echo $is_selected ? 'aria-current="page"' : ''; ?>><?php echo esc_html__( 'Organisation: ', 'scouting-openid-connect' ) . esc_html( $organisation['name'] . ' (' . $organisation_id . ')' ); ?></a>
			<ul id="<?php echo esc_attr( $children_id ); ?>" hidden></ul>
		</li>
		<?php
	}

	/**
	 * Gets one assignment page and the total count for the selected filters.
	 *
	 * @since 2.7.0 Lists SOL role assignments.
	 * @since Unreleased Includes assignments from descendant organisations.
	 *
	 * @param array $filters Normalized filters.
	 * @param int   $page Current page.
	 * @return array{rows: array, total: int}|\WP_Error Assignment results or a database error.
	 */
	private function get_assignments( array $filters, int $page ): array|\WP_Error {
		global $wpdb;
		$assignment_table    = $wpdb->prefix . 'scouting_oidc_sol_role_assignments';
		$roles_table         = $wpdb->prefix . 'scouting_oidc_sol_roles';
		$units_table         = $wpdb->prefix . 'scouting_oidc_sol_organisation_units';
		$organisations_table = $wpdb->prefix . 'scouting_oidc_sol_organisations';
		$organisation_ids    = $this->get_organisation_descendant_ids( $filters['organisation_id'] );

		if ( is_wp_error( $organisation_ids ) ) {
			return $organisation_ids;
		}

		$limit             = self::PAGE_SIZE;
		$offset            = ( $page - 1 ) * $limit;
		$filter_parameters = array(
			$filters['organisation_unit_id'],
			$filters['organisation_unit_id'],
			$filters['role_id'],
			$filters['role_id'],
		);
		$count_parameters  = array_merge(
			array( $assignment_table, $roles_table, $units_table ),
			$organisation_ids,
			$filter_parameters
		);
		$row_parameters    = array_merge(
			array( $assignment_table, $roles_table, $units_table, $organisations_table, $wpdb->users ),
			$organisation_ids,
			$filter_parameters,
			array( $limit, $offset )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i AS assignment ' .
				'INNER JOIN %i AS role ON role.role_id = assignment.role_id ' .
				'INNER JOIN %i AS unit ON unit.organisation_unit_id = role.organisation_unit_id ' .
				'WHERE unit.organisation_id IN (' . implode( ', ', array_fill( 0, count( $organisation_ids ), '%s' ) ) . ') ' .
				'AND ( %d = 0 OR unit.organisation_unit_id = %d ) ' .
				'AND ( %d = 0 OR role.role_id = %d )',
				$count_parameters
			)
		);
		if ( '' !== $wpdb->last_error ) {
			Logger::error( LogComponent::ROLES, 'Could not count SOL role assignments for the selected organisation tree.' );
			return new \WP_Error( 'sol_explorer_assignments_read_failed' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT assignment.user_id, assignment.role_id, assignment.start_date, assignment.end_date, ' .
				'organisation.organisation_id, organisation.name AS organisation_name, ' .
				'unit.organisation_unit_id, unit.name AS unit_name, unit.category AS unit_category, unit.nature AS unit_nature, role.role_name, ' .
				'user.display_name, user.user_login ' .
				'FROM %i AS assignment ' .
				'INNER JOIN %i AS role ON role.role_id = assignment.role_id ' .
				'INNER JOIN %i AS unit ON unit.organisation_unit_id = role.organisation_unit_id ' .
				'INNER JOIN %i AS organisation ON organisation.organisation_id = unit.organisation_id ' .
				'INNER JOIN %i AS user ON user.ID = assignment.user_id ' .
				'WHERE unit.organisation_id IN (' . implode( ', ', array_fill( 0, count( $organisation_ids ), '%s' ) ) . ') ' .
				'AND ( %d = 0 OR unit.organisation_unit_id = %d ) ' .
				'AND ( %d = 0 OR role.role_id = %d ) ' .
				'ORDER BY organisation.name, unit.name, role.role_name, user.display_name, assignment.start_date DESC ' .
				'LIMIT %d OFFSET %d',
				$row_parameters
			),
			ARRAY_A
		);
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			Logger::error( LogComponent::ROLES, 'Could not load SOL role assignments for the selected organisation tree.' );
			return new \WP_Error( 'sol_explorer_assignments_read_failed' );
		}

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Gets an organisation and all of its descendants without revisiting cycles.
	 *
	 * @since Unreleased Includes descendant organisations in organisation filters.
	 *
	 * @param string $organisation_id Selected organisation ID.
	 * @return array<int, string>|\WP_Error Organisation IDs or a database error.
	 */
	private function get_organisation_descendant_ids( string $organisation_id ): array|\WP_Error {
		global $wpdb;

		$parents_table    = $wpdb->prefix . 'scouting_oidc_sol_organisation_parents';
		$organisation_ids = array( $organisation_id );
		$known_ids        = array( $organisation_id => true );
		$frontier         = array( $organisation_id );

		while ( ! empty( $frontier ) ) {
			$parameters = array_merge( array( $parents_table ), $frontier );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$children = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT DISTINCT child_organisation_id FROM %i WHERE parent_organisation_id IN (' . implode( ', ', array_fill( 0, count( $frontier ), '%s' ) ) . ')',
					$parameters
				)
			);
			if ( ! is_array( $children ) || '' !== $wpdb->last_error ) {
				Logger::error( LogComponent::ROLES, 'Could not load descendant SOL organisations for the role explorer.' );
				return new \WP_Error( 'sol_explorer_tree_read_failed' );
			}

			$next_frontier = array();
			foreach ( $children as $child_id ) {
				if ( isset( $known_ids[ $child_id ] ) ) {
					continue;
				}

				$known_ids[ $child_id ] = true;
				$next_frontier[]        = $child_id;
				$organisation_ids[]     = $child_id;
			}
			$frontier = $next_frontier;
		}

		return $organisation_ids;
	}

	/**
	 * Formats an organisation unit name with any available category and nature.
	 *
	 * @since Unreleased Shows synchronized organisation unit metadata.
	 *
	 * @param array $unit Organisation unit or assignment row.
	 * @return string Formatted unit name.
	 */
	private function format_unit_name( array $unit ): string {
		$name     = $unit['name'] ?? $unit['unit_name'] ?? '';
		$category = $unit['category'] ?? $unit['unit_category'] ?? null;
		$nature   = $unit['nature'] ?? $unit['unit_nature'] ?? null;
		$metadata = array();
		if ( is_string( $category ) && '' !== $category ) {
			$metadata[] = $category;
		}
		if ( is_string( $nature ) && '' !== $nature ) {
			$metadata[] = $nature;
		}

		return $name . ( empty( $metadata ) ? '' : ' [' . implode( ' / ', $metadata ) . ']' );
	}

	/**
	 * Displays a stored UTC assignment timestamp as its Netherlands calendar date.
	 *
	 * @since Unreleased Displays API assignment dates without a synthetic time.
	 *
	 * @param string $date Stored UTC database timestamp.
	 * @return string Assignment date in Y-m-d format.
	 */
	private function format_assignment_date( string $date ): string {
		return ( new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) ) )
			->setTimezone( new \DateTimeZone( 'Europe/Amsterdam' ) )
			->format( 'Y-m-d' );
	}

	/**
	 * Renders assignment pagination and retains current filters.
	 *
	 * @since 2.7.0 Paginates SOL role assignments.
	 *
	 * @param array $filters Current filters.
	 * @param int   $page Current page.
	 * @param int   $total Total matching assignments.
	 */
	private function render_pagination( array $filters, int $page, int $total ): void {
		$total_pages = (int) ceil( $total / self::PAGE_SIZE );
		if ( $total_pages < 2 ) {
			return;
		}
		$base_url = add_query_arg(
			array(
				'page'                 => 'scouting-oidc-role-explorer',
				'organisation_id'      => $filters['organisation_id'],
				'organisation_unit_id' => $filters['organisation_unit_id'],
				'role_id'              => $filters['role_id'],
				'paged'                => '%#%',
			),
			admin_url( 'admin.php' )
		);
		echo '<div class="tablenav"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => $base_url,
					'format'  => '',
					'current' => $page,
					'total'   => $total_pages,
				)
			)
		);
		echo '</div></div>';
	}
}
