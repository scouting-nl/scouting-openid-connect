<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.7.0 Adds SOL role administration pages and contextual mapping help.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides administration pages for SOL role mappings and assignments.
 *
 * @since 2.7.0 Adds SOL role administration pages.
 */
class RolesAdmin {
	/**
	 * Option storing SOL role mapping rules.
	 *
	 * @since 2.7.0 Stores SOL role mapping rules.
	 */
	private const RULES_OPTION = 'scouting_oidc_sol_role_mapping_rules';

	/**
	 * Actual WordPress hook for the registered mapping page.
	 *
	 * @since 2.7.0 Loads mapping assets without guessing the menu hook.
	 *
	 * @var string|null
	 */
	private ?string $mapping_page_hook = null;

	/**
	 * Registers admin-post handlers before protected forms can be submitted.
	 *
	 * @since 2.7.0 Registers SOL mapping actions.
	 */
	public function __construct() {
		add_action( 'admin_post_scouting_oidc_roles_save_rule', array( $this, 'scouting_oidc_roles_admin_save_rule' ) );
		add_action( 'admin_post_scouting_oidc_roles_delete_rule', array( $this, 'scouting_oidc_roles_admin_delete_rule' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'scouting_oidc_roles_admin_enqueue_assets' ) );
	}

	/**
	 * Loads progressive enhancements only on the mapping page.
	 *
	 * @since 2.7.0 Loads dialog enhancements before rendering to prevent inline panel flashes.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function scouting_oidc_roles_admin_enqueue_assets( string $hook_suffix ): void {
		if ( $this->mapping_page_hook !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script( 'scouting-oidc-role-mapping', plugins_url( 'role-mapping.js', __FILE__ ), array(), (string) filemtime( __DIR__ . '/role-mapping.js' ), false );
		wp_enqueue_style( 'scouting-oidc-role-mapping', plugins_url( 'role-mapping.css', __FILE__ ), array(), (string) filemtime( __DIR__ . '/role-mapping.css' ) );
	}

	/**
	 * Registers the SOL role administration pages.
	 *
	 * @since 2.7.0 Adds SOL role administration pages.
	 */
	public function scouting_oidc_roles_admin_register_pages(): void {
		if ( ! RolesAuthorization::scouting_oidc_roles_authorization_has_roles_scope() ) {
			return;
		}
		$explorer = new RolesExplorer();

		$mapping_page_hook       = add_submenu_page(
			'scouting-oidc-settings',
			__( 'SOL Role Mapping', 'scouting-openid-connect' ),
			__( 'SOL Role Mapping', 'scouting-openid-connect' ),
			'manage_options',
			'scouting-oidc-role-mapping',
			array( $this, 'scouting_oidc_roles_admin_render_mapping_page' ),
			5
		);
		$this->mapping_page_hook = is_string( $mapping_page_hook ) ? $mapping_page_hook : null;
		if ( null !== $this->mapping_page_hook && '' !== $this->mapping_page_hook ) {
			add_action( 'load-' . $this->mapping_page_hook, array( $this, 'scouting_oidc_roles_admin_register_help_tabs' ) );
		}

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
	 * Registers native WordPress contextual help for the mapping screen.
	 *
	 * @since 2.7.0 Explains rule precedence, editing and user previews.
	 */
	public function scouting_oidc_roles_admin_register_help_tabs(): void {
		$screen = get_current_screen();
		if ( ! ( $screen instanceof \WP_Screen ) ) {
			return;
		}

		$screen->add_help_tab(
			array(
				'id'      => 'scouting_oidc_role_mapping_help_overview',
				'title'   => __( 'Overview', 'scouting-openid-connect' ),
				'content' =>
					'<p>' . esc_html__( 'Every matching allow rule contributes its WordPress role. Duplicate roles are assigned once. Any matching deny rule blocks login, regardless of rule order. New unmatched users receive the WordPress default role. The plugin Settings page can also enable the default role alongside mapped roles.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'All selected conditions in a rule must match the same active SOL assignment. At each successful OIDC login, obsolete plugin-granted roles are removed. Independently managed WordPress roles are retained.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'When Scouts Online supplies parent organisations, an organisation condition also matches active assignments in descendant organisations.', 'scouting-openid-connect' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'scouting_oidc_role_mapping_help_editor',
				'title'   => __( 'Adding and Editing Rules', 'scouting-openid-connect' ),
				'content' =>
					'<p>' . esc_html__( 'Use Add Mapping Rule beside Test a User, or Edit on an existing rule, to open the When / Then editor. Choose at least one organisation, unit or SOL role condition, then allow or deny login. Allowed users receive the selected WordPress role.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'Search results update while typing and retain all selected values. Organisation and unit conditions narrow dependent choices without removing selections. Is one of accepts any selected value; Is not one of excludes those values on the same assignment, not across the whole user. Empty conditions are ignored. Arrow Down or Enter in a search field focuses its results.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'Save or add the rule with the primary button. The close icon, Cancel and Escape close the modal without saving. Without JavaScript or native dialog support, the editor remains inline.', 'scouting-openid-connect' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'scouting_oidc_role_mapping_help_manage',
				'title'   => __( 'Managing Rules', 'scouting-openid-connect' ),
				'content' =>
					'<p>' . esc_html__( 'Rule order does not affect authorization. Duplicate opens an unsaved copy; change its conditions or outcome before saving. Identical rules cannot be saved twice. Delete removes a rule.', 'scouting-openid-connect' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'scouting_oidc_role_mapping_help_test',
				'title'   => __( 'Testing a User', 'scouting-openid-connect' ),
				'content' =>
					'<p>' . esc_html__( 'Test a User accepts an exact WordPress username or user ID. It shows active stored SOL assignments, every matching rule, and the combined login and role decision for an existing user.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'The preview does not include unsaved edits, refresh provider data, change user roles or sign the user in. It includes the optional default role alongside matched roles, but does not apply the new-user fallback. New-user registration and other login checks still apply.', 'scouting-openid-connect' ) . '</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'Need more help?', 'scouting-openid-connect' ) . '</strong></p>' .
			'<p><a href="' . esc_url( admin_url( 'admin.php?page=scouting-oidc-role-explorer' ) ) . '">' . esc_html__( 'Open the SOL Role Explorer', 'scouting-openid-connect' ) . '</a></p>' .
			'<p><a href="' . esc_url( admin_url( 'admin.php?page=scouting-oidc-support' ) ) . '">' . esc_html__( 'Open the Support page', 'scouting-openid-connect' ) . '</a></p>'
		);
	}

	/**
	 * Renders all-match role mapping rules.
	 *
	 * @since 2.7.0 Adds the SOL role mapping page and styled progressive modal editor.
	 */
	public function scouting_oidc_roles_admin_render_mapping_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SOL role mappings.', 'scouting-openid-connect' ) );
		}
		if ( ! RolesAuthorization::scouting_oidc_roles_authorization_has_roles_scope() ) {
			self::scouting_oidc_roles_admin_render_scope_notice();
			return;
		}

		$mapping = $this->scouting_oidc_roles_admin_get_mapping();
		$rules   = $mapping['rules'];
		$editing = null;
		$edit_id = '';
		if ( isset( $_GET['edit'] ) ) {
			check_admin_referer( 'scouting_oidc_roles_edit_rule' );
			$edit_id = sanitize_text_field( wp_unslash( $_GET['edit'] ) );
		} elseif ( isset( $_GET['copy'] ) ) {
			check_admin_referer( 'scouting_oidc_roles_duplicate_rule' );
			$edit_id = sanitize_text_field( wp_unslash( $_GET['copy'] ) );
		}
		foreach ( $rules as $rule ) {
			if ( $rule['id'] === $edit_id ) {
				$editing = $rule;
				if ( isset( $_GET['copy'] ) && ! isset( $_GET['edit'] ) ) {
					$editing['id'] = '';
				}
				break;
			}
		}

		$organisations      = $this->scouting_oidc_roles_admin_get_organisations();
		$organisation_units = $this->scouting_oidc_roles_admin_get_organisation_units();
		$roles              = $this->scouting_oidc_roles_admin_get_roles();
		$wordpress_roles    = wp_roles()->roles;
		$organisation_paths = RolesAuthorization::scouting_oidc_roles_authorization_get_organisation_paths( array_map( 'strval', array_keys( $organisations ) ) );
		if ( is_wp_error( $organisation_paths ) ) {
			wp_die( esc_html__( 'The preview could not read SOL assignments. Check the database before relying on a login decision.', 'scouting-openid-connect' ) );
		}
		?>
		<div class="wrap scouting-oidc-role-mapping">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'SOL Role Mapping', 'scouting-openid-connect' ); ?></h1>
			<hr class="wp-header-end" />
			<?php self::scouting_oidc_roles_admin_render_scope_notice(); ?>
			<?php $this->scouting_oidc_roles_admin_render_notice(); ?>
			<p class="scouting-oidc-mapping-actions">
				<a id="sol-add-rule" class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=scouting-oidc-role-mapping' ) ); ?>#scouting-oidc-rule-editor"><?php esc_html_e( 'Add Mapping Rule', 'scouting-openid-connect' ); ?></a>
				<a id="sol-open-user-test" class="button" href="#sol-user-test-heading"><?php esc_html_e( 'Test a User', 'scouting-openid-connect' ); ?></a>
			</p>
			<p><?php esc_html_e( 'Every matching allow rule contributes its WordPress role. Duplicate roles are assigned once. Any matching deny rule blocks login, regardless of rule order. New unmatched users receive the WordPress default role. The plugin Settings page can also enable the default role alongside mapped roles.', 'scouting-openid-connect' ); ?></p>
			<p><?php esc_html_e( 'All selected conditions in a rule must match the same active SOL assignment. At each successful OIDC login, obsolete plugin-granted roles are removed. Independently managed WordPress roles are retained.', 'scouting-openid-connect' ); ?></p>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'When Scouts Online supplies parent organisations, an organisation condition also matches assignments in its descendant organisations.', 'scouting-openid-connect' ); ?></p></div>
			<div class="scouting-oidc-rule-list">
				<table class="widefat striped">
					<caption class="screen-reader-text"><?php esc_html_e( 'Saved mapping rules', 'scouting-openid-connect' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'When', 'scouting-openid-connect' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Then', 'scouting-openid-connect' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'scouting-openid-connect' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rules ) ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No mapping rules yet.', 'scouting-openid-connect' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rules as $rule ) : ?>
						<?php
						$edit_url   = wp_nonce_url(
							add_query_arg(
								array(
									'page' => 'scouting-oidc-role-mapping',
									'edit' => $rule['id'],
								),
								admin_url( 'admin.php' )
							),
							'scouting_oidc_roles_edit_rule'
						);
						$copy_url   = wp_nonce_url(
							add_query_arg(
								array(
									'page' => 'scouting-oidc-role-mapping',
									'copy' => $rule['id'],
								),
								admin_url( 'admin.php' )
							),
							'scouting_oidc_roles_duplicate_rule'
						);
						$conditions = $this->scouting_oidc_roles_admin_describe_conditions( $rule, $organisations, $organisation_units, $roles );
						$outcome    = $this->scouting_oidc_roles_admin_describe_outcome( $rule, $wordpress_roles );
						/* translators: 1: rule conditions, 2: rule outcome. */
						$actions_label = sprintf( __( 'Actions for rule: %1$s; %2$s', 'scouting-openid-connect' ), $conditions, $outcome );
						?>
						<tr>
							<td><?php echo esc_html( $conditions ); ?></td>
							<td><?php echo esc_html( $outcome ); ?></td>
							<td>
								<div class="scouting-oidc-rule-actions" role="group" aria-label="<?php echo esc_attr( $actions_label ); ?>">
									<a href="<?php echo esc_url( $edit_url ); ?>#scouting-oidc-rule-editor"><?php esc_html_e( 'Edit', 'scouting-openid-connect' ); ?></a>
									<a href="<?php echo esc_url( $copy_url ); ?>#scouting-oidc-rule-editor"><?php esc_html_e( 'Duplicate', 'scouting-openid-connect' ); ?></a>
									<?php $this->scouting_oidc_roles_admin_render_rule_action( 'scouting_oidc_roles_delete_rule', __( 'Delete', 'scouting-openid-connect' ), $rule['id'] ); ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<section id="sol-rule-panel" data-editing="<?php echo $editing ? 'true' : 'false'; ?>" aria-labelledby="scouting-oidc-rule-editor">
				<div class="scouting-oidc-editor-header">
					<h2 id="scouting-oidc-rule-editor" tabindex="-1"><?php echo esc_html( ! empty( $editing['id'] ) ? __( 'Edit Mapping Rule', 'scouting-openid-connect' ) : __( 'Add Mapping Rule', 'scouting-openid-connect' ) ); ?></h2>
					<button class="scouting-oidc-dialog-close sol-close-dialog" type="button" aria-label="<?php esc_attr_e( 'Close', 'scouting-openid-connect' ); ?>" hidden>
						<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
							<path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
						</svg>
					</button>
				</div>
				<form id="sol-rule-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="scouting_oidc_roles_save_rule" />
					<input type="hidden" name="rule_id" value="<?php echo esc_attr( $editing['id'] ?? '' ); ?>" />
					<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'scouting_oidc_roles_save_rule' ) ); ?>" />
					<h3><?php esc_html_e( 'When an active SOL assignment matches', 'scouting-openid-connect' ); ?></h3>
					<details class="scouting-oidc-condition-help">
						<summary><?php esc_html_e( 'Selection and matching help', 'scouting-openid-connect' ); ?></summary>
						<p id="sol-condition-help"><?php esc_html_e( 'Select one or more values per condition using the checkboxes. Values within a condition mean any of those values; all populated conditions must match the same active assignment. Empty conditions impose no restriction. Without JavaScript, use the selection lists and hold Ctrl (Command on Mac) to select multiple values, or use Shift with the arrow keys.', 'scouting-openid-connect' ); ?></p>
						<p><?php esc_html_e( 'Is not one of excludes values on the assignment being checked. It does not mean the user has no such assignments elsewhere. A user without active assignments never matches, including exclusions.', 'scouting-openid-connect' ); ?></p>
					</details>
					<table class="form-table scouting-oidc-condition-fields" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="sol-organisation"><?php esc_html_e( 'Organisation', 'scouting-openid-connect' ); ?></label></th>
								<td>
									<?php $this->scouting_oidc_roles_admin_render_operator( 'organisation', __( 'Organisation condition', 'scouting-openid-connect' ), $editing ); ?>
									<?php $this->scouting_oidc_roles_admin_render_search( 'sol-organisation', __( 'Search organisations', 'scouting-openid-connect' ) ); ?>
									<select id="sol-organisation" name="organisation_ids[]" multiple size="6" aria-describedby="sol-condition-help">
								<?php
								foreach ( $organisations as $organisation ) :
									?>
									<option value="<?php echo esc_attr( $organisation['organisation_id'] ); ?>" <?php selected( in_array( (string) $organisation['organisation_id'], $editing['organisation_ids'] ?? array(), true ) ); ?>><?php echo esc_html( $organisation['name'] . ' (' . $organisation['organisation_id'] . ')' ); ?></option><?php endforeach; ?></select></td>
							</tr>
							<tr>
								<th scope="row"><label for="sol-unit"><?php esc_html_e( 'Organisation Unit', 'scouting-openid-connect' ); ?></label></th>
								<td>
									<?php $this->scouting_oidc_roles_admin_render_operator( 'organisation_unit', __( 'Unit condition', 'scouting-openid-connect' ), $editing ); ?>
									<?php $this->scouting_oidc_roles_admin_render_search( 'sol-unit', __( 'Search units', 'scouting-openid-connect' ) ); ?>
									<select id="sol-unit" name="organisation_unit_ids[]" multiple size="6" aria-describedby="sol-condition-help">
								<?php
								foreach ( $organisation_units as $unit ) :
									?>
									<option value="<?php echo esc_attr( (string) $unit['organisation_unit_id'] ); ?>" data-organisations="<?php echo esc_attr( implode( ' ', $organisation_paths[ $unit['organisation_id'] ] ?? array( $unit['organisation_id'] ) ) ); ?>" <?php selected( in_array( (int) $unit['organisation_unit_id'], $editing['organisation_unit_ids'] ?? array(), true ) ); ?>><?php echo esc_html( $unit['organisation_name'] . ' / ' . $unit['name'] . ' (' . $unit['organisation_unit_id'] . ')' ); ?></option><?php endforeach; ?></select></td>
							</tr>
							<tr>
								<th scope="row"><label for="sol-role"><?php esc_html_e( 'SOL Role', 'scouting-openid-connect' ); ?></label></th>
								<td>
									<?php $this->scouting_oidc_roles_admin_render_operator( 'role', __( 'SOL role condition', 'scouting-openid-connect' ), $editing ); ?>
									<?php $this->scouting_oidc_roles_admin_render_search( 'sol-role', __( 'Search SOL roles', 'scouting-openid-connect' ) ); ?>
									<select id="sol-role" name="role_ids[]" multiple size="6" aria-describedby="sol-condition-help">
								<?php
								foreach ( $roles as $role ) :
									?>
									<option value="<?php echo esc_attr( (string) $role['role_id'] ); ?>" data-organisations="<?php echo esc_attr( implode( ' ', $organisation_paths[ $role['organisation_id'] ] ?? array( $role['organisation_id'] ) ) ); ?>" data-unit="<?php echo esc_attr( (string) $role['organisation_unit_id'] ); ?>" <?php selected( in_array( (int) $role['role_id'], $editing['role_ids'] ?? array(), true ) ); ?>><?php echo esc_html( $role['role_name'] . ' / ' . $role['unit_name'] . ' (' . $role['role_id'] . ')' ); ?></option><?php endforeach; ?></select></td>
							</tr>
						</tbody>
					</table>
					<p id="sol-search-status" role="status" aria-live="polite" aria-atomic="true"></p>
					<span id="sol-search-status-template" hidden><?php esc_html_e( 'Search matches: organisations {organisations}, units {units}, SOL roles {roles}.', 'scouting-openid-connect' ); ?></span>
					<span id="sol-search-result-template" hidden><?php esc_html_e( '{count} matches. Selected values: {selection}.', 'scouting-openid-connect' ); ?></span>
					<h3><?php esc_html_e( 'Then', 'scouting-openid-connect' ); ?></h3>
					<table class="form-table scouting-oidc-outcome-fields" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="login-allowed"><?php esc_html_e( 'Scouting OIDC login', 'scouting-openid-connect' ); ?></label></th>
								<td><select id="login-allowed" name="login_allowed" aria-controls="sol-wordpress-role-row"><option value="1" <?php selected( $editing['login_allowed'] ?? true, true ); ?>><?php esc_html_e( 'Allow login', 'scouting-openid-connect' ); ?></option><option value="0" <?php selected( $editing['login_allowed'] ?? true, false ); ?>><?php esc_html_e( 'Deny login', 'scouting-openid-connect' ); ?></option></select></td>
							</tr>
							<tr id="sol-wordpress-role-row">
								<th scope="row"><label for="wordpress-role"><?php esc_html_e( 'WordPress Role', 'scouting-openid-connect' ); ?></label></th>
								<td><select id="wordpress-role" name="wordpress_role" required>
								<?php
								foreach ( $wordpress_roles as $role_key => $wordpress_role ) :
									?>
									<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( $editing['wordpress_role'] ?? 'subscriber', $role_key ); ?>><?php echo esc_html( translate_user_role( $wordpress_role['name'] ) ); ?></option><?php endforeach; ?></select></td>
							</tr>
						</tbody>
					</table>
					<div class="scouting-oidc-editor-actions">
						<?php submit_button( ! empty( $editing['id'] ) ? __( 'Save Rule', 'scouting-openid-connect' ) : __( 'Add Rule', 'scouting-openid-connect' ), 'primary', 'submit', false ); ?>
						<button class="button sol-close-dialog" type="button" hidden><?php esc_html_e( 'Cancel', 'scouting-openid-connect' ); ?></button>
						<?php
						if ( $editing ) :
							?>
							<a class="button sol-inline-cancel" href="<?php echo esc_url( admin_url( 'admin.php?page=scouting-oidc-role-mapping' ) ); ?>"><?php esc_html_e( 'Cancel', 'scouting-openid-connect' ); ?></a>
						<?php endif; ?>
					</div>
				</form>
			</section>

			<?php $this->scouting_oidc_roles_admin_render_user_test( $organisations, $organisation_units, $roles, $wordpress_roles ); ?>
		</div>
		<?php
	}

	/**
	 * Renders a plain-language inclusion/exclusion operator.
	 *
	 * @since 2.7.0 Provides translated condition operators.
	 *
	 * @param string     $field Condition field.
	 * @param string     $label Accessible operator label.
	 * @param array|null $editing Rule being edited.
	 */
	private function scouting_oidc_roles_admin_render_operator( string $field, string $label, ?array $editing ): void {
		$id = 'sol-' . $field . '-operator';
		?>
		<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $field . '_operator' ); ?>">
			<option value="in" <?php selected( $editing[ $field . '_operator' ] ?? 'in', 'in' ); ?>><?php esc_html_e( 'Is one of', 'scouting-openid-connect' ); ?></option>
			<option value="not_in" <?php selected( $editing[ $field . '_operator' ] ?? 'in', 'not_in' ); ?>><?php esc_html_e( 'Is not one of', 'scouting-openid-connect' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Renders a progressively enhanced search and its local result feedback.
	 *
	 * @since 2.7.0 Shows live search matches next to each native selector.
	 *
	 * @param string $selector_id Associated selector ID.
	 * @param string $label Localized search label.
	 */
	private function scouting_oidc_roles_admin_render_search( string $selector_id, string $label ): void {
		?>
		<div class="scouting-oidc-selector-search" hidden>
			<label for="<?php echo esc_attr( $selector_id . '-search' ); ?>"><?php echo esc_html( $label ); ?></label>
			<input id="<?php echo esc_attr( $selector_id . '-search' ); ?>" type="search" aria-controls="<?php echo esc_attr( $selector_id ); ?>" aria-describedby="<?php echo esc_attr( 'sol-condition-help ' . $selector_id . '-results' ); ?>" />
			<button class="button sol-clear-selection" type="button" aria-controls="<?php echo esc_attr( $selector_id ); ?>"><?php esc_html_e( 'Clear selection', 'scouting-openid-connect' ); ?></button>
			<p id="<?php echo esc_attr( $selector_id . '-results' ); ?>" class="description" role="status" aria-atomic="true"></p>
		</div>
		<?php
	}

	/**
	 * Warns administrators when SOL role data is not requested.
	 *
	 * @since 2.7.0 Shows the same scope guidance on both SOL role pages.
	 */
	public static function scouting_oidc_roles_admin_render_scope_notice(): void {
		if ( RolesAuthorization::scouting_oidc_roles_authorization_has_roles_scope() ) {
			return;
		}
		?>
		<div class="notice notice-warning" role="status">
			<p><?php esc_html_e( 'The roles scope is not enabled. SOL role pages and role-dependent settings are hidden. Saved mapping and product organisation/role rules are paused, not deleted. Existing WordPress roles are unchanged; new users receive the WordPress default role. WooCommerce SOL sign-in requirements remain active.', 'scouting-openid-connect' ); ?></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=scouting-oidc-settings' ) ); ?>"><?php esc_html_e( 'Open plugin Settings to enable the roles scope', 'scouting-openid-connect' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Saves a mapping rule after capability, nonce and field validation.
	 *
	 * @since 2.7.0 Saves SOL mapping rules without duplicates.
	 */
	public function scouting_oidc_roles_admin_save_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SOL role mappings.', 'scouting-openid-connect' ) );
		}
		check_admin_referer( 'scouting_oidc_roles_save_rule' );
		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$rule    = $this->scouting_oidc_roles_admin_validate_rule( $_POST );
		if ( is_wp_error( $rule ) ) {
			$this->scouting_oidc_roles_admin_redirect( 'sol_hierarchy_read_failed' === $rule->get_error_code() ? 'save-failed' : 'invalid' );
		}

		if ( ! $this->scouting_oidc_roles_admin_lock_mapping( true ) ) {
			$this->scouting_oidc_roles_admin_redirect( 'save-failed' );
		}
		try {
			$notice = $this->scouting_oidc_roles_admin_store_rule( $rule, $rule_id );
		} finally {
			$released = $this->scouting_oidc_roles_admin_lock_mapping( false );
		}
		$this->scouting_oidc_roles_admin_redirect( $released ? $notice : 'save-failed' );
	}

	/**
	 * Stores a validated rule while the mapping lock is held.
	 *
	 * @since 2.7.0 Rejects identical rules on both add and edit.
	 *
	 * @param array  $rule Validated rule with normalized conditions.
	 * @param string $rule_id Existing rule ID, or empty for a new rule.
	 * @return string Result notice identifier.
	 */
	private function scouting_oidc_roles_admin_store_rule( array $rule, string $rule_id ): string {
		$mapping  = $this->scouting_oidc_roles_admin_get_mapping();
		$original = $mapping;
		foreach ( $mapping['rules'] as $existing_rule ) {
			if ( $existing_rule['id'] === $rule_id ) {
				continue;
			}
			$comparison       = $existing_rule;
			$comparison['id'] = '';
			if ( ! $rule['login_allowed'] && ! $comparison['login_allowed'] ) {
				$comparison['wordpress_role'] = $rule['wordpress_role'];
			}
			if ( $comparison === $rule ) {
				return 'duplicate';
			}
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
				return 'invalid';
			}
		} else {
			$rule['id']         = wp_generate_uuid4();
			$mapping['rules'][] = $rule;
		}

		if ( $original !== $mapping && ! update_option( self::RULES_OPTION, $mapping, false ) ) {
			return 'save-failed';
		}
		return 'saved';
	}

	/**
	 * Deletes a mapping rule.
	 *
	 * @since 2.7.0 Deletes SOL mapping rules.
	 */
	public function scouting_oidc_roles_admin_delete_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SOL role mappings.', 'scouting-openid-connect' ) );
		}
		check_admin_referer( 'scouting_oidc_roles_delete_rule' );
		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		if ( ! $this->scouting_oidc_roles_admin_lock_mapping( true ) ) {
			$this->scouting_oidc_roles_admin_redirect( 'save-failed' );
		}
		try {
			$mapping          = $this->scouting_oidc_roles_admin_get_mapping();
			$original         = $mapping;
			$mapping['rules'] = array_values(
				array_filter(
					$mapping['rules'],
					static fn( array $rule ): bool => $rule['id'] !== $rule_id
				)
			);
			$notice           = $original !== $mapping && ! update_option( self::RULES_OPTION, $mapping, false ) ? 'save-failed' : 'deleted';
		} finally {
			$released = $this->scouting_oidc_roles_admin_lock_mapping( false );
		}
		$this->scouting_oidc_roles_admin_redirect( $released ? $notice : 'save-failed' );
	}

	/**
	 * Serializes mapping changes and refreshes the option after acquiring the lock.
	 *
	 * @since 2.7.0 Prevents overlapping submissions from duplicating or overwriting rules.
	 *
	 * @param bool $acquire Whether to acquire or release the database lock.
	 * @return bool Whether the lock operation succeeded.
	 */
	private function scouting_oidc_roles_admin_lock_mapping( bool $acquire ): bool {
		global $wpdb;
		$lock_name = 'scouting_oidc_mapping_' . md5( DB_NAME . ':' . $wpdb->options );
		if ( $acquire ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Connection-scoped advisory locks must not be cached.
			$result = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Connection-scoped advisory locks must not be cached.
			$result = $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
		if ( '1' !== (string) $result ) {
			Logger::error( LogComponent::ROLES, 'Could not acquire or release the SOL role mapping write lock.' );
			return false;
		}
		if ( $acquire ) {
			wp_cache_delete( self::RULES_OPTION, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			wp_cache_delete( 'alloptions', 'options' );
		}
		return true;
	}

	/**
	 * Describes a mapping outcome without implying denied users receive a role.
	 *
	 * @since 2.7.0 Simplifies mapping summaries.
	 *
	 * @param array $rule Mapping rule or authorization decision.
	 * @param array $wordpress_roles Registered WordPress roles.
	 * @return string Localized outcome.
	 */
	private function scouting_oidc_roles_admin_describe_outcome( array $rule, array $wordpress_roles ): string {
		if ( ! $rule['login_allowed'] ) {
			return __( 'Deny login', 'scouting-openid-connect' );
		}
		$role_slugs = $rule['wordpress_roles'] ?? array( $rule['wordpress_role'] );
		if ( empty( $role_slugs ) ) {
			return __( 'Allow login; remove obsolete plugin-granted roles without adding a role', 'scouting-openid-connect' );
		}
		$names = array_map( static fn( string $role ): string => translate_user_role( $wordpress_roles[ $role ]['name'] ?? $role ), $role_slugs );
		/* translators: %s: comma-separated WordPress role names. */
		return sprintf( __( 'Allow login; assign %s', 'scouting-openid-connect' ), implode( ', ', $names ) );
	}

	/**
	 * Tests a saved mapping against a user's synchronized active assignments.
	 *
	 * @since 2.7.0 Adds a read-only authorization tester with grouped results and a complete active-assignment table.
	 *
	 * @param array $organisations Synchronized organisations.
	 * @param array $units Synchronized units.
	 * @param array $roles Synchronized roles.
	 * @param array $wordpress_roles Registered WordPress roles.
	 */
	private function scouting_oidc_roles_admin_render_user_test( array $organisations, array $units, array $roles, array $wordpress_roles ): void {
		$query = '';
		$user  = false;
		$error = '';
		if ( isset( $_POST['scouting_oidc_test_user'] ) ) {
			check_admin_referer( 'scouting_oidc_roles_test_user' );
			$query = isset( $_POST['test_user'] ) ? sanitize_text_field( wp_unslash( $_POST['test_user'] ) ) : '';
			$user  = get_user_by( 'login', $query );
			if ( ! $user && ctype_digit( $query ) ) {
				$user = get_user_by( 'id', (int) $query );
			}
			if ( ! $user ) {
				$error = __( 'No WordPress user was found. Enter an exact username or user ID.', 'scouting-openid-connect' );
			}
		}
		?>
			<section id="sol-user-test-panel" data-submitted="<?php echo isset( $_POST['scouting_oidc_test_user'] ) ? 'true' : 'false'; ?>" aria-labelledby="sol-user-test-heading">
			<div class="scouting-oidc-editor-header">
				<h2 id="sol-user-test-heading" tabindex="-1"><?php esc_html_e( 'Test a User', 'scouting-openid-connect' ); ?></h2>
				<button class="scouting-oidc-dialog-close sol-close-dialog" type="button" aria-label="<?php esc_attr_e( 'Close', 'scouting-openid-connect' ); ?>" hidden>
					<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
						<path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
					</svg>
				</button>
			</div>
			<div class="scouting-oidc-user-test-body">
			<p id="sol-user-test-help"><?php esc_html_e( 'Preview saved rules using currently stored active SOL assignments. Unsaved edits are not included. This does not sign the user in, refresh SOL data, or change their roles.', 'scouting-openid-connect' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=scouting-oidc-role-mapping' ) ); ?>#sol-user-test-result">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'scouting_oidc_roles_test_user' ) ); ?>" />
				<label for="sol-test-user"><?php esc_html_e( 'Username or user ID', 'scouting-openid-connect' ); ?></label>
				<input id="sol-test-user" name="test_user" type="text" value="<?php echo esc_attr( $query ); ?>" aria-describedby="sol-user-test-help<?php echo '' !== $error ? ' sol-user-test-error' : ''; ?>" <?php echo '' !== $error ? 'aria-invalid="true"' : ''; ?> required />
				<div class="scouting-oidc-editor-actions">
					<?php submit_button( __( 'Test User', 'scouting-openid-connect' ), 'primary', 'scouting_oidc_test_user', false ); ?>
					<button class="button sol-close-dialog" type="button" hidden><?php esc_html_e( 'Cancel', 'scouting-openid-connect' ); ?></button>
				</div>
			</form>
			<div id="sol-user-test-result" tabindex="-1" <?php echo '' === $error && ! $user ? 'hidden' : ''; ?>>
			<?php if ( '' !== $error ) : ?>
				<p id="sol-user-test-error" class="notice notice-error inline" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php elseif ( $user ) : ?>
				<?php
				$preview = RolesAuthorization::scouting_oidc_roles_authorization_get_user_preview( $user->ID );
				?>
				<?php if ( is_wp_error( $preview ) ) : ?>
					<p class="notice notice-error inline" role="alert"><?php echo esc_html( 'sol_assignments_unavailable' === $preview->get_error_code() ? $preview->get_error_message() : __( 'The preview could not read SOL assignments. Check the database before relying on a login decision.', 'scouting-openid-connect' ) ); ?></p>
				<?php else : ?>
					<h3><?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?></h3>
					<div class="notice inline <?php echo $preview['decision']['login_allowed'] ? 'notice-success' : 'notice-error'; ?>">
						<p><strong><?php echo esc_html( $this->scouting_oidc_roles_admin_describe_outcome( $preview['decision'], $wordpress_roles ) ); ?></strong></p>
						<?php
						if ( empty( $preview['rule_indexes'] ) ) {
							?>
							<p><?php esc_html_e( 'No matching rules: remove obsolete plugin-granted roles without adding a role for this existing user.', 'scouting-openid-connect' ); ?></p>
							<?php
						}
						?>
					</div>
					<?php if ( ! empty( $preview['rule_indexes'] ) ) : ?>
						<?php $saved_mapping = $this->scouting_oidc_roles_admin_get_mapping(); ?>
						<h4><?php esc_html_e( 'Matching rules', 'scouting-openid-connect' ); ?></h4>
						<ul class="scouting-oidc-test-rules">
							<?php foreach ( $preview['rule_indexes'] as $rule_index ) : ?>
								<li>
									<p><?php echo esc_html( $this->scouting_oidc_roles_admin_describe_conditions( $saved_mapping['rules'][ $rule_index ], $organisations, $units, $roles ) ); ?></p>
									<p><strong><?php echo esc_html( $this->scouting_oidc_roles_admin_describe_outcome( $saved_mapping['rules'][ $rule_index ], $wordpress_roles ) ); ?></strong></p>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<?php if ( empty( $preview['assignments'] ) ) : ?>
						<h4><?php esc_html_e( 'Active SOL assignments', 'scouting-openid-connect' ); ?></h4>
						<p><?php esc_html_e( 'No stored active SOL assignments.', 'scouting-openid-connect' ); ?></p>
					<?php else : ?>
						<div class="scouting-oidc-test-assignments">
							<table class="widefat striped">
								<caption><?php esc_html_e( 'Active SOL assignments', 'scouting-openid-connect' ); ?></caption>
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Organisation', 'scouting-openid-connect' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Organisation Unit', 'scouting-openid-connect' ); ?></th>
										<th scope="col"><?php esc_html_e( 'SOL Role', 'scouting-openid-connect' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $preview['assignments'] as $assignment ) : ?>
										<tr>
											<td><?php echo esc_html( ( $organisations[ $assignment['organisation_id'] ]['name'] ?? $assignment['organisation_id'] ) . ' (' . $assignment['organisation_id'] . ')' ); ?></td>
											<td><?php echo esc_html( ( $units[ $assignment['organisation_unit_id'] ]['name'] ?? $assignment['organisation_unit_id'] ) . ' (' . $assignment['organisation_unit_id'] . ')' ); ?></td>
											<td><?php echo esc_html( ( $roles[ $assignment['role_id'] ]['role_name'] ?? $assignment['role_id'] ) . ' (' . $assignment['role_id'] . ')' ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
			</div>
			</div>
			</section>
			<?php
	}

	/**
	 * Validates mapping rule fields against synchronized source data.
	 *
	 * @since 2.7.0 Validates SOL mapping rules.
	 *
	 * @param array $input Unsanitized form fields.
	 * @return array|\WP_Error Normalized mapping rule or a validation error.
	 */
	private function scouting_oidc_roles_admin_validate_rule( array $input ): array|\WP_Error {
		$conditions = RolesAuthorization::scouting_oidc_roles_authorization_normalize_conditions( wp_unslash( $input ) );
		if ( is_wp_error( $conditions ) ) {
			return $conditions;
		}
		$wordpress_role     = isset( $input['wordpress_role'] ) ? sanitize_key( wp_unslash( $input['wordpress_role'] ) ) : '';
		$login_allowed      = isset( $input['login_allowed'] ) ? sanitize_text_field( wp_unslash( $input['login_allowed'] ) ) : '';
		$wordpress_roles    = wp_roles()->roles;
		$units              = $this->scouting_oidc_roles_admin_get_organisation_units();
		$roles              = $this->scouting_oidc_roles_admin_get_roles();
		$organisation_paths = RolesAuthorization::scouting_oidc_roles_authorization_get_organisation_paths( array_map( 'strval', array_column( $units, 'organisation_id' ) ) );
		if ( is_wp_error( $organisation_paths ) ) {
			return $organisation_paths;
		}

		$available = array(
			'organisation'      => $this->scouting_oidc_roles_admin_get_organisations(),
			'organisation_unit' => $units,
			'role'              => $roles,
		);
		foreach ( $available as $field => $choices ) {
			foreach ( $conditions[ $field . '_ids' ] as $identifier ) {
				if ( ! isset( $choices[ $identifier ] ) ) {
					return new \WP_Error( 'invalid_rule' );
				}
			}
		}
		if ( ! isset( $wordpress_roles[ $wordpress_role ] ) || ! in_array( $login_allowed, array( '0', '1' ), true ) ) {
			return new \WP_Error( 'invalid_rule' );
		}

		return array_merge(
			array( 'id' => '' ),
			$conditions,
			array(
				'wordpress_role' => $wordpress_role,
				'login_allowed'  => '1' === $login_allowed,
			)
		);
	}

	/**
	 * Gets the configured mapping rules.
	 *
	 * @since 2.7.0 Reads SOL mapping configuration.
	 *
	 * @return array{rules: array} Mapping configuration.
	 */
	private function scouting_oidc_roles_admin_get_mapping(): array {
		return RolesAuthorization::scouting_oidc_roles_authorization_get_mapping();
	}

	/**
	 * Gets synchronized organisations keyed by their exact ID.
	 *
	 * @since 2.7.0 Lists synchronized organisations.
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
	 * @since 2.7.0 Lists synchronized organisation units.
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
	 * @since 2.7.0 Lists synchronized SOL roles.
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
	 * @since 2.7.0 Adds mapping rule actions.
	 *
	 * @param string $action Form action name.
	 * @param string $label Visible and accessible action label.
	 * @param string $rule_id Mapping rule ID.
	 */
	private function scouting_oidc_roles_admin_render_rule_action( string $action, string $label, string $rule_id ): void {
		?>
		<form class="inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
			<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule_id ); ?>" />
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( $action ) ); ?>" />
			<button class="button-link" type="submit"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * Generates readable condition text for a mapping rule.
	 *
	 * @since 2.7.0 Describes SOL mapping conditions.
	 *
	 * @param array $rule Mapping rule.
	 * @param array $organisations Organisations keyed by ID.
	 * @param array $units Organisation units keyed by ID.
	 * @param array $roles Roles keyed by ID.
	 * @return string Human-readable rule conditions.
	 */
	private function scouting_oidc_roles_admin_describe_conditions( array $rule, array $organisations, array $units, array $roles ): string {
		$conditions = array();
		if ( isset( $rule['organisation_ids'] ) ) {
			$fields = array(
				'organisation'      => array( __( 'Organisation', 'scouting-openid-connect' ), $organisations, 'name' ),
				'organisation_unit' => array( __( 'Organisation Unit', 'scouting-openid-connect' ), $units, 'name' ),
				'role'              => array( __( 'SOL Role', 'scouting-openid-connect' ), $roles, 'role_name' ),
			);
			foreach ( $fields as $field => $details ) {
				list( $label, $choices, $name_key ) = $details;
				if ( empty( $rule[ $field . '_ids' ] ) ) {
					continue;
				}
				$names = array_map( static fn( $id ): string => ( $choices[ $id ][ $name_key ] ?? (string) $id ) . ' (' . $id . ')', $rule[ $field . '_ids' ] );
				/* translators: 1: condition label, 2: inclusion/exclusion operator, 3: selected values. */
				$conditions[] = sprintf( __( '%1$s: %2$s [%3$s]', 'scouting-openid-connect' ), $label, 'not_in' === $rule[ $field . '_operator' ] ? __( 'Is not one of', 'scouting-openid-connect' ) : __( 'Is one of', 'scouting-openid-connect' ), implode( ', ', $names ) );
			}
			return implode( '; ', $conditions );
		}
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

		return implode( '; ', $conditions );
	}

	/**
	 * Renders action results on either role page.
	 *
	 * @since 2.7.0 Reports role administration actions.
	 */
	private function scouting_oidc_roles_admin_render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value only selects a fixed, escaped status message.
		$message  = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
		$messages = array(
			'save-failed' => __( 'The role mapping could not be saved. Please try again or check the database.', 'scouting-openid-connect' ),
			'duplicate'   => __( 'An identical mapping rule already exists. No duplicate was saved.', 'scouting-openid-connect' ),
			'saved'       => __( 'Role mapping saved.', 'scouting-openid-connect' ),
			'deleted'     => __( 'Role mapping deleted.', 'scouting-openid-connect' ),
			'invalid'     => __( 'The rule was invalid or referenced data that is no longer synchronized.', 'scouting-openid-connect' ),
		);
		if ( isset( $messages[ $message ] ) ) {
			$is_error = in_array( $message, array( 'invalid', 'save-failed', 'duplicate' ), true );
			printf( '<div class="notice notice-%1$s is-dismissible" role="%2$s"><p>%3$s</p></div>', $is_error ? 'error' : 'success', $is_error ? 'alert' : 'status', esc_html( $messages[ $message ] ) );
		}
	}

	/**
	 * Redirects to the mapping page after a completed action.
	 *
	 * @since 2.7.0 Returns to the mapping page after an action.
	 *
	 * @param string $notice Notice identifier.
	 */
	private function scouting_oidc_roles_admin_redirect( string $notice ): void {
		if ( 'save-failed' === $notice ) {
			Logger::error( LogComponent::ROLES, 'SOL role mapping changes could not be saved.' );
		}
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
