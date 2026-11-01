<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since Unreleased Adds SOL role explorer.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays synchronized SOL roles and their user assignments.
 *
 * @since Unreleased Adds SOL role explorer.
 */
class RolesExplorer {
	/**
	 * Assignments displayed per page.
	 *
	 * @since Unreleased Paginates SOL role assignments.
	 */
	private const PAGE_SIZE = 50;

	/**
	 * Renders organisation, unit, role and assigned-user filters.
	 *
	 * @since Unreleased Displays SOL assignments without an email column.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view SOL assignments.', 'scouting-openid-connect' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- These query parameters only filter read-only administrator data.
		$filters = array(
			'organisation_id'      => isset( $_GET['organisation_id'] ) ? sanitize_text_field( wp_unslash( $_GET['organisation_id'] ) ) : '',
			'organisation_unit_id' => isset( $_GET['organisation_unit_id'] ) ? absint( $_GET['organisation_unit_id'] ) : 0,
			'role_id'              => isset( $_GET['role_id'] ) ? absint( $_GET['role_id'] ) : 0,
		);
		$page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$options = $this->get_filter_options( $filters );
		$filters = $options['filters'];
		$data    = '' === $filters['organisation_id'] ? array(
			'rows'  => array(),
			'total' => 0,
		) : $this->get_assignments( $filters, $page );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SOL Role Explorer', 'scouting-openid-connect' ); ?></h1>
			<p><?php esc_html_e( 'Browse organisations, units and roles, then inspect synchronized user assignments and their dates.', 'scouting-openid-connect' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="scouting-oidc-role-explorer" />
				<label for="filter-organisation"><?php esc_html_e( 'Organisation', 'scouting-openid-connect' ); ?></label>
				<select id="filter-organisation" name="organisation_id">
					<option value=""><?php esc_html_e( 'Select an organisation', 'scouting-openid-connect' ); ?></option>
					<?php foreach ( $options['organisations'] as $organisation ) : ?>
						<option value="<?php echo esc_attr( $organisation['organisation_id'] ); ?>" <?php selected( $filters['organisation_id'], $organisation['organisation_id'] ); ?>><?php echo esc_html( $organisation['name'] . ' (' . $organisation['organisation_id'] . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="filter-unit"><?php esc_html_e( 'Organisation Unit', 'scouting-openid-connect' ); ?></label>
				<select id="filter-unit" name="organisation_unit_id" <?php disabled( '' === $filters['organisation_id'] ); ?>>
					<option value="0"><?php echo esc_html( '' === $filters['organisation_id'] ? __( 'Choose an organisation first', 'scouting-openid-connect' ) : __( 'All', 'scouting-openid-connect' ) ); ?></option>
					<?php foreach ( $options['units'] as $unit ) : ?>
						<option value="<?php echo esc_attr( (string) $unit['organisation_unit_id'] ); ?>" <?php selected( $filters['organisation_unit_id'], (int) $unit['organisation_unit_id'] ); ?>><?php echo esc_html( $unit['name'] . ' (' . $unit['organisation_unit_id'] . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="filter-role"><?php esc_html_e( 'SOL Role', 'scouting-openid-connect' ); ?></label>
				<select id="filter-role" name="role_id" <?php disabled( 0 === $filters['organisation_unit_id'] ); ?>>
					<option value="0"><?php echo esc_html( 0 === $filters['organisation_unit_id'] ? __( 'Choose an organisation unit first', 'scouting-openid-connect' ) : __( 'All', 'scouting-openid-connect' ) ); ?></option>
					<?php foreach ( $options['roles'] as $role ) : ?>
						<option value="<?php echo esc_attr( (string) $role['role_id'] ); ?>" <?php selected( $filters['role_id'], (int) $role['role_id'] ); ?>><?php echo esc_html( $role['role_name'] . ' (' . $role['role_id'] . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Filter', 'scouting-openid-connect' ), 'secondary', '', false ); ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=scouting-oidc-role-explorer' ) ); ?>"><?php esc_html_e( 'Reset', 'scouting-openid-connect' ); ?></a>
			</form>
			<?php if ( '' !== $filters['organisation_id'] ) : ?>
				<p>
					<?php
					/* translators: %s: number of matching SOL role assignments. */
					echo esc_html( sprintf( _n( '%s assignment found.', '%s assignments found.', $data['total'], 'scouting-openid-connect' ), number_format_i18n( $data['total'] ) ) );
					?>
				</p>
			<?php endif; ?>
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
					<tr><td colspan="6"><?php esc_html_e( 'Select an organisation to view role assignments.', 'scouting-openid-connect' ); ?></td></tr>
				<?php elseif ( empty( $data['rows'] ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No role assignments match these filters.', 'scouting-openid-connect' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $data['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['organisation_name'] . ' (' . $row['organisation_id'] . ')' ); ?></td>
						<td><?php echo esc_html( $row['unit_name'] . ' (' . $row['organisation_unit_id'] . ')' ); ?></td>
						<td><?php echo esc_html( $row['role_name'] . ' (' . $row['role_id'] . ')' ); ?></td>
						<td><a href="<?php echo esc_url( get_edit_user_link( (int) $row['user_id'] ) ); ?>"><?php echo esc_html( $row['display_name'] . ' (' . $row['user_login'] . ')' ); ?></a></td>
						<td><?php echo esc_html( $row['start_date'] ); ?></td>
						<td><?php echo esc_html( $row['end_date'] ?? __( 'No end date', 'scouting-openid-connect' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php $this->render_pagination( $filters, $page, $data['total'] ); ?>
		</div>
		<?php
	}

	/**
	 * Gets scoped organisations, units and roles for the filter controls.
	 *
	 * @since Unreleased Lists synchronized SOL role data.
	 *
	 * @param array $filters Requested filters.
	 * @return array{filters: array, organisations: array, units: array, roles: array} Normalized filters and filter options.
	 */
	private function get_filter_options( array $filters ): array {
		global $wpdb;
		$organisations_table = $wpdb->prefix . 'scouting_oidc_sol_organisations';
		$units_table         = $wpdb->prefix . 'scouting_oidc_sol_organisation_units';
		$roles_table         = $wpdb->prefix . 'scouting_oidc_sol_roles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$organisations    = $wpdb->get_results( $wpdb->prepare( 'SELECT organisation_id, name FROM %i ORDER BY name, organisation_id', $organisations_table ), ARRAY_A );
		$organisations    = is_array( $organisations ) ? $organisations : array();
		$organisation_ids = array_column( $organisations, 'organisation_id' );
		if ( '' !== $filters['organisation_id'] && ! in_array( $filters['organisation_id'], $organisation_ids, true ) ) {
			$filters['organisation_id']      = '';
			$filters['organisation_unit_id'] = 0;
			$filters['role_id']              = 0;
		}

		$units = array();
		if ( '' !== $filters['organisation_id'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$units = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT organisation_unit_id, organisation_id, name FROM %i WHERE organisation_id = %s ORDER BY name, organisation_unit_id',
					$units_table,
					$filters['organisation_id']
				),
				ARRAY_A
			);
			$units = is_array( $units ) ? $units : array();
		}

		$unit_ids = array_map( 'intval', array_column( $units, 'organisation_unit_id' ) );
		if ( 0 !== $filters['organisation_unit_id'] && ! in_array( $filters['organisation_unit_id'], $unit_ids, true ) ) {
			$filters['organisation_unit_id'] = 0;
			$filters['role_id']              = 0;
		}

		$roles = array();
		if ( 0 !== $filters['organisation_unit_id'] ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$roles = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT role_id, role_name FROM %i WHERE organisation_unit_id = %d ORDER BY role_name, role_id',
					$roles_table,
					$filters['organisation_unit_id']
				),
				ARRAY_A
			);
			$roles = is_array( $roles ) ? $roles : array();
		}

		$role_ids = array_map( 'intval', array_column( $roles, 'role_id' ) );
		if ( 0 !== $filters['role_id'] && ! in_array( $filters['role_id'], $role_ids, true ) ) {
			$filters['role_id'] = 0;
		}

		return array(
			'filters'       => $filters,
			'organisations' => $organisations,
			'units'         => $units,
			'roles'         => $roles,
		);
	}

	/**
	 * Gets one assignment page and the total count for the selected filters.
	 *
	 * @since Unreleased Lists SOL role assignments.
	 *
	 * @param array $filters Normalized filters.
	 * @param int   $page Current page.
	 * @return array{rows: array, total: int} Assignment results.
	 */
	private function get_assignments( array $filters, int $page ): array {
		global $wpdb;
		$assignment_table    = $wpdb->prefix . 'scouting_oidc_sol_role_assignments';
		$roles_table         = $wpdb->prefix . 'scouting_oidc_sol_roles';
		$units_table         = $wpdb->prefix . 'scouting_oidc_sol_organisation_units';
		$organisations_table = $wpdb->prefix . 'scouting_oidc_sol_organisations';

		$limit  = self::PAGE_SIZE;
		$offset = ( $page - 1 ) * $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i AS assignment ' .
				'INNER JOIN %i AS role ON role.role_id = assignment.role_id ' .
				'INNER JOIN %i AS unit ON unit.organisation_unit_id = role.organisation_unit_id ' .
				'WHERE ( %s = \'\' OR unit.organisation_id = %s ) ' .
				'AND ( %d = 0 OR unit.organisation_unit_id = %d ) ' .
				'AND ( %d = 0 OR role.role_id = %d )',
				$assignment_table,
				$roles_table,
				$units_table,
				$filters['organisation_id'],
				$filters['organisation_id'],
				$filters['organisation_unit_id'],
				$filters['organisation_unit_id'],
				$filters['role_id'],
				$filters['role_id']
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT assignment.user_id, assignment.role_id, assignment.start_date, assignment.end_date, ' .
				'organisation.organisation_id, organisation.name AS organisation_name, ' .
				'unit.organisation_unit_id, unit.name AS unit_name, role.role_name, ' .
				'user.display_name, user.user_login ' .
				'FROM %i AS assignment ' .
				'INNER JOIN %i AS role ON role.role_id = assignment.role_id ' .
				'INNER JOIN %i AS unit ON unit.organisation_unit_id = role.organisation_unit_id ' .
				'INNER JOIN %i AS organisation ON organisation.organisation_id = unit.organisation_id ' .
				'INNER JOIN %i AS user ON user.ID = assignment.user_id ' .
				'WHERE ( %s = \'\' OR unit.organisation_id = %s ) ' .
				'AND ( %d = 0 OR unit.organisation_unit_id = %d ) ' .
				'AND ( %d = 0 OR role.role_id = %d ) ' .
				'ORDER BY organisation.name, unit.name, role.role_name, user.display_name, assignment.start_date DESC ' .
				'LIMIT %d OFFSET %d',
				$assignment_table,
				$roles_table,
				$units_table,
				$organisations_table,
				$wpdb->users,
				$filters['organisation_id'],
				$filters['organisation_id'],
				$filters['organisation_unit_id'],
				$filters['organisation_unit_id'],
				$filters['role_id'],
				$filters['role_id'],
				$limit,
				$offset
			),
			ARRAY_A
		);

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Renders assignment pagination and retains current filters.
	 *
	 * @since Unreleased Paginates SOL role assignments.
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
