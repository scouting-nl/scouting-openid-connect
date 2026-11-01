<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since Unreleased Adds SOL role administration pages.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides administration pages for SOL role mappings and assignments.
 *
 * @since Unreleased Adds SOL role administration pages.
 */
class RolesAdmin {
	/**
	 * Option storing ordered SOL role mapping rules.
	 *
	 * @since Unreleased Stores SOL role mapping rules.
	 */
	private const RULES_OPTION = 'scouting_oidc_sol_role_mapping_rules';

	/**
	 * Registers admin-post handlers before protected forms can be submitted.
	 *
	 * @since Unreleased Registers SOL mapping actions.
	 */
	public function __construct() {
		add_action( 'admin_post_scouting_oidc_roles_save_rule', array( $this, 'scouting_oidc_roles_admin_save_rule' ) );
		add_action( 'admin_post_scouting_oidc_roles_delete_rule', array( $this, 'scouting_oidc_roles_admin_delete_rule' ) );
		add_action( 'admin_post_scouting_oidc_roles_move_rule', array( $this, 'scouting_oidc_roles_admin_move_rule' ) );
		add_action( 'admin_post_scouting_oidc_roles_save_default', array( $this, 'scouting_oidc_roles_admin_save_default' ) );
	}

	/**
	 * Registers the SOL role administration pages.
	 *
	 * @since Unreleased Adds SOL role administration pages.
	 */
	public function scouting_oidc_roles_admin_register_pages(): void {
		$explorer = new RolesExplorer();

		add_submenu_page(
			'scouting-oidc-settings',
			__( 'SOL Role Mapping', 'scouting-openid-connect' ),
			__( 'SOL Role Mapping', 'scouting-openid-connect' ),
			'manage_options',
			'scouting-oidc-role-mapping',
			array( $this, 'scouting_oidc_roles_admin_render_mapping_page' ),
			5
		);

		add_submenu_page(
			'scouting-oidc-settings',
			__( 'SOL Role Explorer', 'scouting-openid-connect' ),
			__( 'SOL Role Explorer', 'scouting-openid-connect' ),
			'manage_options',
			'scouting-oidc-role-explorer',
			array( $explorer, 'render_page' ),
			6
		);
	}

	/**
	 * Renders ordered role mapping rules and the fallback rule.
	 *
	 * @since Unreleased Adds the SOL role mapping page.
	 */
	public function scouting_oidc_roles_admin_render_mapping_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SOL role mappings.', 'scouting-openid-connect' ) );
		}

		$mapping = $this->scouting_oidc_roles_admin_get_mapping();
		$rules   = $mapping['rules'];
		$editing = null;
		$edit_id = '';
		if ( isset( $_GET['edit'] ) ) {
			check_admin_referer( 'scouting_oidc_roles_edit_rule' );
			$edit_id = sanitize_text_field( wp_unslash( $_GET['edit'] ) );
		}
		foreach ( $rules as $rule ) {
			if ( $rule['id'] === $edit_id ) {
				$editing = $rule;
				break;
			}
		}

		$organisations      = $this->scouting_oidc_roles_admin_get_organisations();
		$organisation_units = $this->scouting_oidc_roles_admin_get_organisation_units();
		$roles              = $this->scouting_oidc_roles_admin_get_roles();
		$wordpress_roles    = wp_roles()->roles;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SOL Role Mapping', 'scouting-openid-connect' ); ?></h1>
			<p><?php esc_html_e( 'Rules are evaluated from top to bottom. The first matching rule wins; the default rule is always evaluated last.', 'scouting-openid-connect' ); ?></p>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'These rules are applied after a successful Scouting OIDC login. The first matching rule assigns its WordPress role and determines whether the user may sign in.', 'scouting-openid-connect' ); ?></p></div>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'When Scouts Online supplies parent organisations, an organisation condition also matches assignments in its descendant organisations.', 'scouting-openid-connect' ); ?></p></div>
			<?php $this->scouting_oidc_roles_admin_render_notice(); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Number', 'scouting-openid-connect' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Conditions', 'scouting-openid-connect' ); ?></th>
						<th scope="col"><?php esc_html_e( 'WordPress Role', 'scouting-openid-connect' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Login Allowed', 'scouting-openid-connect' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'scouting-openid-connect' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rules ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No mapping rules yet.', 'scouting-openid-connect' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rules as $index => $rule ) : ?>
					<?php
					$edit_url = wp_nonce_url(
						add_query_arg(
							array(
								'page' => 'scouting-oidc-role-mapping',
								'edit' => $rule['id'],
							),
							admin_url( 'admin.php' )
						),
						'scouting_oidc_roles_edit_rule'
					);
					?>
					<tr>
						<td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
						<td><?php echo esc_html( $this->scouting_oidc_roles_admin_describe_conditions( $rule, $organisations, $organisation_units, $roles ) ); ?></td>
						<td><?php echo esc_html( $wordpress_roles[ $rule['wordpress_role'] ]['name'] ?? $rule['wordpress_role'] ); ?></td>
						<td><?php echo esc_html( $rule['login_allowed'] ? __( 'Yes', 'scouting-openid-connect' ) : __( 'No', 'scouting-openid-connect' ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'scouting-openid-connect' ); ?></a>
							<?php $this->scouting_oidc_roles_admin_render_rule_action( 'scouting_oidc_roles_move_rule', __( 'Move up', 'scouting-openid-connect' ), $rule['id'], 'up', $index > 0 ); ?>
							<?php $this->scouting_oidc_roles_admin_render_rule_action( 'scouting_oidc_roles_move_rule', __( 'Move down', 'scouting-openid-connect' ), $rule['id'], 'down', $index < count( $rules ) - 1 ); ?>
							<?php $this->scouting_oidc_roles_admin_render_rule_action( 'scouting_oidc_roles_delete_rule', __( 'Delete', 'scouting-openid-connect' ), $rule['id'] ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
					<tr>
						<td><?php esc_html_e( 'Default', 'scouting-openid-connect' ); ?></td>
						<td><?php esc_html_e( 'Every user who does not match an earlier rule', 'scouting-openid-connect' ); ?></td>
						<td><?php echo esc_html( $wordpress_roles[ $mapping['default']['wordpress_role'] ]['name'] ?? $mapping['default']['wordpress_role'] ); ?></td>
						<td><?php echo esc_html( $mapping['default']['login_allowed'] ? __( 'Yes', 'scouting-openid-connect' ) : __( 'No', 'scouting-openid-connect' ) ); ?></td>
						<td><?php esc_html_e( 'Always last', 'scouting-openid-connect' ); ?></td>
					</tr>
				</tbody>
			</table>

			<h2><?php echo esc_html( $editing ? __( 'Edit Mapping Rule', 'scouting-openid-connect' ) : __( 'Add Mapping Rule', 'scouting-openid-connect' ) ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="scouting_oidc_roles_save_rule" />
				<input type="hidden" name="rule_id" value="<?php echo esc_attr( $editing['id'] ?? '' ); ?>" />
				<?php wp_nonce_field( 'scouting_oidc_roles_save_rule' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="sol-organisation"><?php esc_html_e( 'Organisation', 'scouting-openid-connect' ); ?></label></th>
							<td><select id="sol-organisation" name="organisation_id"><option value=""><?php esc_html_e( 'Any organisation', 'scouting-openid-connect' ); ?></option>
							<?php
							foreach ( $organisations as $organisation ) :
								?>
								<option value="<?php echo esc_attr( $organisation['organisation_id'] ); ?>" <?php selected( $editing['organisation_id'] ?? '', $organisation['organisation_id'] ); ?>><?php echo esc_html( $organisation['name'] . ' (' . $organisation['organisation_id'] . ')' ); ?></option><?php endforeach; ?></select></td>
						</tr>
						<tr>
							<th scope="row"><label for="sol-unit"><?php esc_html_e( 'Organisation Unit', 'scouting-openid-connect' ); ?></label></th>
							<td><select id="sol-unit" name="organisation_unit_id"><option value="0"><?php esc_html_e( 'Any unit', 'scouting-openid-connect' ); ?></option>
							<?php
							foreach ( $organisation_units as $unit ) :
								?>
								<option value="<?php echo esc_attr( (string) $unit['organisation_unit_id'] ); ?>" <?php selected( (string) ( $editing['organisation_unit_id'] ?? 0 ), (string) $unit['organisation_unit_id'] ); ?>><?php echo esc_html( $unit['name'] . ' (' . $unit['organisation_unit_id'] . ')' ); ?></option><?php endforeach; ?></select></td>
						</tr>
						<tr>
							<th scope="row"><label for="sol-role"><?php esc_html_e( 'SOL Role', 'scouting-openid-connect' ); ?></label></th>
							<td><select id="sol-role" name="role_id"><option value="0"><?php esc_html_e( 'Any role', 'scouting-openid-connect' ); ?></option>
							<?php
							foreach ( $roles as $role ) :
								?>
								<option value="<?php echo esc_attr( (string) $role['role_id'] ); ?>" <?php selected( (string) ( $editing['role_id'] ?? 0 ), (string) $role['role_id'] ); ?>><?php echo esc_html( $role['role_name'] . ' (' . $role['role_id'] . ')' ); ?></option><?php endforeach; ?></select></td>
						</tr>
						<tr>
							<th scope="row"><label for="wordpress-role"><?php esc_html_e( 'WordPress Role', 'scouting-openid-connect' ); ?></label></th>
							<td><select id="wordpress-role" name="wordpress_role" required>
							<?php
							foreach ( $wordpress_roles as $role_key => $wordpress_role ) :
								?>
								<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $editing['wordpress_role'] ?? 'subscriber', $role_key ); ?>><?php echo esc_html( translate_user_role( $wordpress_role['name'] ) ); ?></option><?php endforeach; ?></select></td>
						</tr>
						<tr>
							<th scope="row"><label for="login-allowed"><?php esc_html_e( 'Login Allowed', 'scouting-openid-connect' ); ?></label></th>
							<td><select id="login-allowed" name="login_allowed"><option value="1" <?php selected( $editing['login_allowed'] ?? true, true ); ?>><?php esc_html_e( 'Yes', 'scouting-openid-connect' ); ?></option><option value="0" <?php selected( $editing['login_allowed'] ?? true, false ); ?>><?php esc_html_e( 'No', 'scouting-openid-connect' ); ?></option></select></td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( $editing ? __( 'Save Rule', 'scouting-openid-connect' ) : __( 'Add Rule', 'scouting-openid-connect' ) ); ?>
				<?php
				if ( $editing ) :
					?>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=scouting-oidc-role-mapping' ) ); ?>"><?php esc_html_e( 'Cancel', 'scouting-openid-connect' ); ?></a><?php endif; ?>
			</form>

			<h2><?php esc_html_e( 'Default Rule', 'scouting-openid-connect' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="scouting_oidc_roles_save_default" />
				<?php wp_nonce_field( 'scouting_oidc_roles_save_default' ); ?>
				<label for="default-wordpress-role"><?php esc_html_e( 'WordPress Role', 'scouting-openid-connect' ); ?></label>
				<select id="default-wordpress-role" name="wordpress_role">
				<?php
				foreach ( $wordpress_roles as $role_key => $wordpress_role ) :
					?>
					<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $mapping['default']['wordpress_role'], $role_key ); ?>><?php echo esc_html( translate_user_role( $wordpress_role['name'] ) ); ?></option><?php endforeach; ?></select>
				<label for="default-login-allowed"><?php esc_html_e( 'Login Allowed', 'scouting-openid-connect' ); ?></label>
				<select id="default-login-allowed" name="login_allowed"><option value="1" <?php selected( $mapping['default']['login_allowed'], true ); ?>><?php esc_html_e( 'Yes', 'scouting-openid-connect' ); ?></option><option value="0" <?php selected( $mapping['default']['login_allowed'], false ); ?>><?php esc_html_e( 'No', 'scouting-openid-connect' ); ?></option></select>
				<?php submit_button( __( 'Save Default', 'scouting-openid-connect' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Saves a mapping rule after capability, nonce and field validation.
	 *
	 * @since Unreleased Saves ordered SOL mapping rules.
	 */
	public function scouting_oidc_roles_admin_save_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SOL role mappings.', 'scouting-openid-connect' ) );
		}
		check_admin_referer( 'scouting_oidc_roles_save_rule' );
		$mapping = $this->scouting_oidc_roles_admin_get_mapping();
		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$rule    = $this->scouting_oidc_roles_admin_validate_rule( $_POST );
		if ( is_wp_error( $rule ) ) {
			$this->scouting_oidc_roles_admin_redirect( 'invalid' );
		}

		if ( '' !== $rule_id ) {
			$found = false;
			foreach ( $mapping['rules'] as &$existing_rule ) {
				if ( $existing_rule['id'] === $rule_id ) {
					$rule['id']    = $rule_id;
					$existing_rule = $rule;
					$found         = true;
					break;
				}
			}
			unset( $existing_rule );
			if ( ! $found ) {
				$this->scouting_oidc_roles_admin_redirect( 'invalid' );
			}
		} else {
			$rule['id']         = wp_generate_uuid4();
			$mapping['rules'][] = $rule;
		}

		update_option( self::RULES_OPTION, $mapping, false );
		$this->scouting_oidc_roles_admin_redirect( 'saved' );
	}

	/**
	 * Deletes a mapping rule.
	 *
	 * @since Unreleased Deletes SOL mapping rules.
	 */
	public function scouting_oidc_roles_admin_delete_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SOL role mappings.', 'scouting-openid-connect' ) );
		}
		check_admin_referer( 'scouting_oidc_roles_delete_rule' );
		$rule_id          = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$mapping          = $this->scouting_oidc_roles_admin_get_mapping();
		$mapping['rules'] = array_values(
			array_filter(
				$mapping['rules'],
				static fn( array $rule ): bool => $rule['id'] !== $rule_id
			)
		);
		update_option( self::RULES_OPTION, $mapping, false );
		$this->scouting_oidc_roles_admin_redirect( 'deleted' );
	}

	/**
	 * Moves a mapping rule up or down one position.
	 *
	 * @since Unreleased Reorders SOL mapping rules.
	 */
	public function scouting_oidc_roles_admin_move_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SOL role mappings.', 'scouting-openid-connect' ) );
		}
		check_admin_referer( 'scouting_oidc_roles_move_rule' );
		$rule_id   = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$direction = isset( $_POST['direction'] ) ? sanitize_key( wp_unslash( $_POST['direction'] ) ) : '';
		$mapping   = $this->scouting_oidc_roles_admin_get_mapping();
		$index     = array_search( $rule_id, array_column( $mapping['rules'], 'id' ), true );
		$offset    = 0;
		if ( 'up' === $direction ) {
			$offset = -1;
		} elseif ( 'down' === $direction ) {
			$offset = 1;
		}
		$target = false === $index ? -1 : $index + $offset;
		if ( 0 !== $offset && isset( $mapping['rules'][ $target ] ) ) {
			$current_rule                = $mapping['rules'][ $index ];
			$mapping['rules'][ $index ]  = $mapping['rules'][ $target ];
			$mapping['rules'][ $target ] = $current_rule;
			update_option( self::RULES_OPTION, $mapping, false );
		}

		$this->scouting_oidc_roles_admin_redirect( 'saved' );
	}

	/**
	 * Saves the always-last fallback mapping.
	 *
	 * @since Unreleased Saves the SOL mapping default.
	 */
	public function scouting_oidc_roles_admin_save_default(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SOL role mappings.', 'scouting-openid-connect' ) );
		}
		check_admin_referer( 'scouting_oidc_roles_save_default' );
		$mapping = $this->scouting_oidc_roles_admin_get_mapping();
		$role    = isset( $_POST['wordpress_role'] ) ? sanitize_key( wp_unslash( $_POST['wordpress_role'] ) ) : '';
		$allowed = isset( $_POST['login_allowed'] ) ? sanitize_text_field( wp_unslash( $_POST['login_allowed'] ) ) : '';
		$roles   = wp_roles()->roles;
		if ( ! isset( $roles[ $role ] ) || ! in_array( $allowed, array( '0', '1' ), true ) ) {
			$this->scouting_oidc_roles_admin_redirect( 'invalid' );
		}

		$mapping['default'] = array(
			'wordpress_role' => $role,
			'login_allowed'  => '1' === $allowed,
		);
		update_option( self::RULES_OPTION, $mapping, false );
		$this->scouting_oidc_roles_admin_redirect( 'saved' );
	}

	/**
	 * Validates mapping rule fields against synchronized source data.
	 *
	 * @since Unreleased Validates SOL mapping rules.
	 *
	 * @param array $input Unsanitized form fields.
	 * @return array|\WP_Error Normalized mapping rule or a validation error.
	 */
	private function scouting_oidc_roles_admin_validate_rule( array $input ): array|\WP_Error {
		$organisation_id = isset( $input['organisation_id'] ) ? sanitize_text_field( wp_unslash( $input['organisation_id'] ) ) : '';
		$unit_id         = isset( $input['organisation_unit_id'] ) ? absint( $input['organisation_unit_id'] ) : 0;
		$role_id         = isset( $input['role_id'] ) ? absint( $input['role_id'] ) : 0;
		$wordpress_role  = isset( $input['wordpress_role'] ) ? sanitize_key( wp_unslash( $input['wordpress_role'] ) ) : '';
		$login_allowed   = isset( $input['login_allowed'] ) ? sanitize_text_field( wp_unslash( $input['login_allowed'] ) ) : '';
		$wordpress_roles = wp_roles()->roles;
		$units           = $this->scouting_oidc_roles_admin_get_organisation_units();
		$roles           = $this->scouting_oidc_roles_admin_get_roles();

		if ( '' !== $organisation_id && ! isset( $this->scouting_oidc_roles_admin_get_organisations()[ $organisation_id ] ) ) {
			return new \WP_Error( 'invalid_organisation' );
		}
		if ( $unit_id > 0 && ! isset( $units[ $unit_id ] ) ) {
			return new \WP_Error( 'invalid_organisation_unit' );
		}
		if ( $role_id > 0 && ! isset( $roles[ $role_id ] ) ) {
			return new \WP_Error( 'invalid_role' );
		}
		if ( $unit_id > 0 && '' !== $organisation_id && $units[ $unit_id ]['organisation_id'] !== $organisation_id ) {
			return new \WP_Error( 'invalid_rule' );
		}
		if ( $role_id > 0 && $unit_id > 0 && (int) $roles[ $role_id ]['organisation_unit_id'] !== $unit_id ) {
			return new \WP_Error( 'invalid_rule' );
		}
		if ( $role_id > 0 && '' !== $organisation_id && $roles[ $role_id ]['organisation_id'] !== $organisation_id ) {
			return new \WP_Error( 'invalid_rule' );
		}
		if ( ! isset( $wordpress_roles[ $wordpress_role ] ) || ! in_array( $login_allowed, array( '0', '1' ), true ) || ( '' === $organisation_id && 0 === $unit_id && 0 === $role_id ) ) {
			return new \WP_Error( 'invalid_rule' );
		}

		return array(
			'organisation_id'      => $organisation_id,
			'organisation_unit_id' => $unit_id,
			'role_id'              => $role_id,
			'wordpress_role'       => $wordpress_role,
			'login_allowed'        => '1' === $login_allowed,
		);
	}

	/**
	 * Gets the configured mapping, using the site's configured default role.
	 *
	 * @since Unreleased Reads SOL mapping configuration.
	 *
	 * @return array{rules: array, default: array{wordpress_role: string, login_allowed: bool}} Mapping configuration.
	 */
	private function scouting_oidc_roles_admin_get_mapping(): array {
		return RolesAuthorization::scouting_oidc_roles_authorization_get_mapping();
	}

	/**
	 * Gets synchronized organisations keyed by their exact ID.
	 *
	 * @since Unreleased Lists synchronized organisations.
	 *
	 * @return array<string, array> Organisations keyed by ID.
	 */
	private function scouting_oidc_roles_admin_get_organisations(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'scouting_oidc_sol_organisations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT organisation_id, name FROM %i ORDER BY name, organisation_id', $table ), ARRAY_A );

		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result[ $row['organisation_id'] ] = $row;
		}

		return $result;
	}

	/**
	 * Gets synchronized organisation units keyed by their ID.
	 *
	 * @since Unreleased Lists synchronized organisation units.
	 *
	 * @return array<int, array> Organisation units keyed by ID.
	 */
	private function scouting_oidc_roles_admin_get_organisation_units(): array {
		global $wpdb;
		$units_table         = $wpdb->prefix . 'scouting_oidc_sol_organisation_units';
		$organisations_table = $wpdb->prefix . 'scouting_oidc_sol_organisations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT unit.organisation_unit_id, unit.organisation_id, unit.name, organisation.name AS organisation_name FROM %i AS unit LEFT JOIN %i AS organisation ON organisation.organisation_id = unit.organisation_id ORDER BY organisation.name, unit.name, unit.organisation_unit_id',
				$units_table,
				$organisations_table
			),
			ARRAY_A
		);

		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result[ (int) $row['organisation_unit_id'] ] = $row;
		}

		return $result;
	}

	/**
	 * Gets synchronized roles keyed by their provider ID.
	 *
	 * @since Unreleased Lists synchronized SOL roles.
	 *
	 * @return array<int, array> Roles keyed by ID.
	 */
	private function scouting_oidc_roles_admin_get_roles(): array {
		global $wpdb;
		$roles_table = $wpdb->prefix . 'scouting_oidc_sol_roles';
		$units_table = $wpdb->prefix . 'scouting_oidc_sol_organisation_units';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT role.role_id, role.organisation_unit_id, role.role_name, role.role_type, unit.organisation_id, unit.name AS unit_name FROM %i AS role LEFT JOIN %i AS unit ON unit.organisation_unit_id = role.organisation_unit_id ORDER BY role.role_name, role.role_id',
				$roles_table,
				$units_table
			),
			ARRAY_A
		);

		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$result[ (int) $row['role_id'] ] = $row;
		}

		return $result;
	}

	/**
	 * Displays action forms for a mapping rule.
	 *
	 * @since Unreleased Adds mapping rule actions.
	 *
	 * @param string $action Form action name.
	 * @param string $label Visible and accessible action label.
	 * @param string $rule_id Mapping rule ID.
	 * @param string $direction Optional move direction.
	 * @param bool   $enabled Whether the action is available.
	 */
	private function scouting_oidc_roles_admin_render_rule_action( string $action, string $label, string $rule_id, string $direction = '', bool $enabled = true ): void {
		if ( ! $enabled ) {
			return;
		}
		?>
		<form class="inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
			<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule_id ); ?>" />
			<?php
			if ( '' !== $direction ) :
				?>
				<input type="hidden" name="direction" value="<?php echo esc_attr( $direction ); ?>" /><?php endif; ?>
			<?php wp_nonce_field( $action ); ?>
			<button class="button-link" type="submit"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * Generates readable condition text for a mapping rule.
	 *
	 * @since Unreleased Describes SOL mapping conditions.
	 *
	 * @param array $rule Mapping rule.
	 * @param array $organisations Organisations keyed by ID.
	 * @param array $units Organisation units keyed by ID.
	 * @param array $roles Roles keyed by ID.
	 * @return string Human-readable rule conditions.
	 */
	private function scouting_oidc_roles_admin_describe_conditions( array $rule, array $organisations, array $units, array $roles ): string {
		$conditions = array();
		if ( '' !== $rule['organisation_id'] ) {
			$name = $organisations[ $rule['organisation_id'] ]['name'] ?? $rule['organisation_id'];
			/* translators: 1: organisation name, 2: organisation ID. */
			$conditions[] = sprintf( __( 'Organisation is %1$s (%2$s)', 'scouting-openid-connect' ), $name, $rule['organisation_id'] );
		}
		if ( $rule['organisation_unit_id'] > 0 ) {
			$name = $units[ $rule['organisation_unit_id'] ]['name'] ?? (string) $rule['organisation_unit_id'];
			/* translators: 1: organisation unit name, 2: organisation unit ID. */
			$conditions[] = sprintf( __( 'Unit is %1$s (%2$d)', 'scouting-openid-connect' ), $name, $rule['organisation_unit_id'] );
		}
		if ( $rule['role_id'] > 0 ) {
			$name = $roles[ $rule['role_id'] ]['role_name'] ?? (string) $rule['role_id'];
			/* translators: 1: SOL role name, 2: SOL role ID. */
			$conditions[] = sprintf( __( 'SOL role is %1$s (%2$d)', 'scouting-openid-connect' ), $name, $rule['role_id'] );
		}

		return implode( ' AND ', $conditions );
	}

	/**
	 * Renders action results on either role page.
	 *
	 * @since Unreleased Reports role administration actions.
	 */
	private function scouting_oidc_roles_admin_render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value only selects a fixed, escaped status message.
		$message  = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
		$messages = array(
			'saved'   => __( 'Role mapping saved.', 'scouting-openid-connect' ),
			'deleted' => __( 'Role mapping deleted.', 'scouting-openid-connect' ),
			'invalid' => __( 'The rule was invalid or referenced data that is no longer synchronized.', 'scouting-openid-connect' ),
		);
		if ( isset( $messages[ $message ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', 'invalid' === $message ? 'error' : 'success', esc_html( $messages[ $message ] ) );
		}
	}

	/**
	 * Redirects to the mapping page after a completed action.
	 *
	 * @since Unreleased Returns to the mapping page after an action.
	 *
	 * @param string $notice Notice identifier.
	 */
	private function scouting_oidc_roles_admin_redirect( string $notice ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => 'scouting-oidc-role-mapping',
					'notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
