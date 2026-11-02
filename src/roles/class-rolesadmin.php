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
	 * Option storing ordered SOL role mapping rules.
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
		add_action( 'admin_post_scouting_oidc_roles_move_rule', array( $this, 'scouting_oidc_roles_admin_move_rule' ) );
		add_action( 'admin_post_scouting_oidc_roles_duplicate_rule', array( $this, 'scouting_oidc_roles_admin_duplicate_rule' ) );
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
					'<p>' . esc_html__( 'Mapping rules control Scouting OIDC login and the WordPress role managed by this plugin. Rules are evaluated from top to bottom; only the first matching rule applies. When no rule matches, the WordPress default role is used and role mapping does not block login. The General Settings option for creating new users still applies.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'All selected conditions must match the same active SOL assignment. A user may have multiple SOL assignments, but matching rules do not accumulate WordPress roles. Independently managed WordPress roles are retained.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'When Scouts Online supplies parent organisations, an organisation condition also matches active assignments in descendant organisations.', 'scouting-openid-connect' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'scouting_oidc_role_mapping_help_editor',
				'title'   => __( 'Adding and Editing Rules', 'scouting-openid-connect' ),
				'content' =>
					'<p>' . esc_html__( 'Use Add Mapping Rule beside Test a User, or Edit on an existing rule, to open the When / Then editor. Choose at least one organisation, unit or SOL role condition, then allow or deny login. Allowed users receive the selected WordPress role.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'Search results update while typing. Searching retains the current selection; choose a result to change it. Organisation and unit selections narrow dependent choices. Arrow Down or Enter in a search field focuses its results.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'Save or add the rule with the primary button. The close icon, Cancel and Escape close the modal without saving. Without JavaScript or native dialog support, the editor remains inline.', 'scouting-openid-connect' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'scouting_oidc_role_mapping_help_order',
				'title'   => __( 'Rule Ordering', 'scouting-openid-connect' ),
				'content' =>
					'<p>' . esc_html__( 'Use Move up, Move down or Position to change rule precedence. Put more specific rules before broader rules when they should take priority. A later deny rule does not override an earlier matching allow rule.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'Duplicate appends a saved copy at the end and opens it for editing. Change its conditions or outcome and move it if needed. Delete removes a rule.', 'scouting-openid-connect' ) . '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'scouting_oidc_role_mapping_help_test',
				'title'   => __( 'Testing a User', 'scouting-openid-connect' ),
				'content' =>
					'<p>' . esc_html__( 'Test a User accepts an exact WordPress username or user ID. It shows active stored SOL assignments, the winning saved rule, and the resulting login and role decision.', 'scouting-openid-connect' ) . '</p>' .
					'<p>' . esc_html__( 'The preview does not include unsaved edits, refresh provider data, change user roles or sign the user in. If no rule matches, it shows the WordPress default role. This previews role mapping only, not other login or new-user registration checks. Updated provider data is synchronized during Scouting OIDC login.', 'scouting-openid-connect' ) . '</p>',
			)
		);

		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'Need more help?', 'scouting-openid-connect' ) . '</strong></p>' .
			'<p><a href="' . esc_url( admin_url( 'admin.php?page=scouting-oidc-role-explorer' ) ) . '">' . esc_html__( 'Open the SOL Role Explorer', 'scouting-openid-connect' ) . '</a></p>' .
			'<p><a href="' . esc_url( admin_url( 'admin.php?page=scouting-oidc-support' ) ) . '">' . esc_html__( 'Open the Support page', 'scouting-openid-connect' ) . '</a></p>'
		);
	}

	/**
	 * Renders ordered role mapping rules without a separate fallback setting.
	 *
	 * @since 2.7.0 Adds the SOL role mapping page and styled progressive modal editor.
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
		$organisation_paths = RolesAuthorization::scouting_oidc_roles_authorization_get_organisation_paths( array_map( 'strval', array_keys( $organisations ) ) );
		if ( is_wp_error( $organisation_paths ) ) {
			wp_die( esc_html__( 'The preview could not read SOL assignments. Check the database before relying on a login decision.', 'scouting-openid-connect' ) );
		}
		?>
		<div class="wrap scouting-oidc-role-mapping">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'SOL Role Mapping', 'scouting-openid-connect' ); ?></h1>
			<hr class="wp-header-end" />
			<?php $this->scouting_oidc_roles_admin_render_notice(); ?>
			<p class="scouting-oidc-mapping-actions">
				<a id="sol-add-rule" class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=scouting-oidc-role-mapping' ) ); ?>#scouting-oidc-rule-editor"><?php esc_html_e( 'Add Mapping Rule', 'scouting-openid-connect' ); ?></a>
				<a id="sol-open-user-test" class="button" href="#sol-user-test-heading"><?php esc_html_e( 'Test a User', 'scouting-openid-connect' ); ?></a>
			</p>
			<p><?php esc_html_e( 'Rules are evaluated from top to bottom. The first matching rule wins. When no rule matches, the WordPress default role is used; the General Settings option for creating new users still applies.', 'scouting-openid-connect' ); ?></p>
			<p><?php esc_html_e( 'The first matching rule allows or denies Scouting OIDC login. Allowed users receive one plugin-managed WordPress role; other independently managed roles are retained. All selected conditions must match the same active SOL assignment.', 'scouting-openid-connect' ); ?></p>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'When Scouts Online supplies parent organisations, an organisation condition also matches assignments in its descendant organisations.', 'scouting-openid-connect' ); ?></p></div>
			<div class="scouting-oidc-rule-list">
				<table class="widefat striped">
					<caption class="screen-reader-text"><?php esc_html_e( 'Saved rules in evaluation order', 'scouting-openid-connect' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Number', 'scouting-openid-connect' ); ?></th>
							<th scope="col"><?php esc_html_e( 'When', 'scouting-openid-connect' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Then', 'scouting-openid-connect' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'scouting-openid-connect' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rules ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No mapping rules yet.', 'scouting-openid-connect' ); ?></td></tr>
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
						/* translators: %d: rule position. */
						$actions_label = sprintf( __( 'Actions for rule %d', 'scouting-openid-connect' ), $index + 1 );
						?>
						<tr>
							<td><?php echo esc_html( (string) ( $index + 1 ) ); ?></td>
							<td><?php echo esc_html( $this->scouting_oidc_roles_admin_describe_conditions( $rule, $organisations, $organisation_units, $roles ) ); ?></td>
							<td><?php echo esc_html( $this->scouting_oidc_roles_admin_describe_outcome( $rule, $wordpress_roles ) ); ?></td>
							<td>
								<div class="scouting-oidc-rule-actions" role="group" aria-label="<?php echo esc_attr( $actions_label ); ?>">
									<a href="<?php echo esc_url( $edit_url ); ?>#scouting-oidc-rule-editor"><?php esc_html_e( 'Edit', 'scouting-openid-connect' ); ?></a>
									<?php $this->scouting_oidc_roles_admin_render_rule_action( 'scouting_oidc_roles_duplicate_rule', __( 'Duplicate', 'scouting-openid-connect' ), $rule['id'] ); ?>
									<?php $this->scouting_oidc_roles_admin_render_rule_action( 'scouting_oidc_roles_move_rule', __( 'Move up', 'scouting-openid-connect' ), $rule['id'], 'up', $index > 0 ); ?>
									<?php $this->scouting_oidc_roles_admin_render_rule_action( 'scouting_oidc_roles_move_rule', __( 'Move down', 'scouting-openid-connect' ), $rule['id'], 'down', $index < count( $rules ) - 1 ); ?>
									<?php $this->scouting_oidc_roles_admin_render_rule_action( 'scouting_oidc_roles_delete_rule', __( 'Delete', 'scouting-openid-connect' ), $rule['id'] ); ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="scouting_oidc_roles_move_rule" />
										<input type="hidden" name="rule_id" value="<?php echo esc_attr( $rule['id'] ); ?>" />
										<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'scouting_oidc_roles_move_rule' ) ); ?>" />
										<label for="<?php echo esc_attr( 'rule-position-' . $index ); ?>"><?php esc_html_e( 'Position', 'scouting-openid-connect' ); ?></label>
										<input id="<?php echo esc_attr( 'rule-position-' . $index ); ?>" type="number" name="position" min="1" max="<?php echo esc_attr( (string) count( $rules ) ); ?>" value="<?php echo esc_attr( (string) ( $index + 1 ) ); ?>" required />
										<button class="button button-small" type="submit"><?php esc_html_e( 'Move', 'scouting-openid-connect' ); ?></button>
									</form>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<section id="sol-rule-panel" data-editing="<?php echo $editing ? 'true' : 'false'; ?>" aria-labelledby="scouting-oidc-rule-editor">
				<div class="scouting-oidc-editor-header">
					<h2 id="scouting-oidc-rule-editor" tabindex="-1"><?php echo esc_html( $editing ? __( 'Edit Mapping Rule', 'scouting-openid-connect' ) : __( 'Add Mapping Rule', 'scouting-openid-connect' ) ); ?></h2>
					<button class="scouting-oidc-dialog-close sol-close-dialog" type="button" aria-label="<?php esc_attr_e( 'Close', 'scouting-openid-connect' ); ?>" hidden>
						<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
							<path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
						</svg>
					</button>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="scouting_oidc_roles_save_rule" />
					<input type="hidden" name="rule_id" value="<?php echo esc_attr( $editing['id'] ?? '' ); ?>" />
					<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'scouting_oidc_roles_save_rule' ) ); ?>" />
					<h3><?php esc_html_e( 'When an active SOL assignment matches', 'scouting-openid-connect' ); ?></h3>
					<p id="sol-condition-help"><?php esc_html_e( 'Choose at least one condition. Search results appear as you type. Searching keeps the current selection; choose a result to change it. Changing an organisation or unit clears incompatible selections.', 'scouting-openid-connect' ); ?></p>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="sol-organisation"><?php esc_html_e( 'Organisation', 'scouting-openid-connect' ); ?></label></th>
								<td>
									<?php $this->scouting_oidc_roles_admin_render_search( 'sol-organisation', __( 'Search organisations', 'scouting-openid-connect' ) ); ?>
									<select id="sol-organisation" name="organisation_id" aria-describedby="sol-condition-help"><option value=""><?php esc_html_e( 'Any organisation', 'scouting-openid-connect' ); ?></option>
								<?php
								foreach ( $organisations as $organisation ) :
									?>
									<option value="<?php echo esc_attr( $organisation['organisation_id'] ); ?>" <?php selected( $editing['organisation_id'] ?? '', $organisation['organisation_id'] ); ?>><?php echo esc_html( $organisation['name'] . ' (' . $organisation['organisation_id'] . ')' ); ?></option><?php endforeach; ?></select></td>
							</tr>
							<tr>
								<th scope="row"><label for="sol-unit"><?php esc_html_e( 'Organisation Unit', 'scouting-openid-connect' ); ?></label></th>
								<td>
									<?php $this->scouting_oidc_roles_admin_render_search( 'sol-unit', __( 'Search units', 'scouting-openid-connect' ) ); ?>
									<select id="sol-unit" name="organisation_unit_id" aria-describedby="sol-condition-help"><option value="0"><?php esc_html_e( 'Any unit', 'scouting-openid-connect' ); ?></option>
								<?php
								foreach ( $organisation_units as $unit ) :
									?>
									<option value="<?php echo esc_attr( (string) $unit['organisation_unit_id'] ); ?>" data-organisations="<?php echo esc_attr( implode( ' ', $organisation_paths[ $unit['organisation_id'] ] ?? array( $unit['organisation_id'] ) ) ); ?>" <?php selected( (string) ( $editing['organisation_unit_id'] ?? 0 ), (string) $unit['organisation_unit_id'] ); ?>><?php echo esc_html( $unit['organisation_name'] . ' / ' . $unit['name'] . ' (' . $unit['organisation_unit_id'] . ')' ); ?></option><?php endforeach; ?></select></td>
							</tr>
							<tr>
								<th scope="row"><label for="sol-role"><?php esc_html_e( 'SOL Role', 'scouting-openid-connect' ); ?></label></th>
								<td>
									<?php $this->scouting_oidc_roles_admin_render_search( 'sol-role', __( 'Search SOL roles', 'scouting-openid-connect' ) ); ?>
									<select id="sol-role" name="role_id" aria-describedby="sol-condition-help"><option value="0"><?php esc_html_e( 'Any role', 'scouting-openid-connect' ); ?></option>
								<?php
								foreach ( $roles as $role ) :
									?>
									<option value="<?php echo esc_attr( (string) $role['role_id'] ); ?>" data-organisations="<?php echo esc_attr( implode( ' ', $organisation_paths[ $role['organisation_id'] ] ?? array( $role['organisation_id'] ) ) ); ?>" data-unit="<?php echo esc_attr( (string) $role['organisation_unit_id'] ); ?>" <?php selected( (string) ( $editing['role_id'] ?? 0 ), (string) $role['role_id'] ); ?>><?php echo esc_html( $role['role_name'] . ' / ' . $role['unit_name'] . ' (' . $role['role_id'] . ')' ); ?></option><?php endforeach; ?></select></td>
							</tr>
						</tbody>
					</table>
					<p id="sol-search-status" role="status" aria-live="polite" aria-atomic="true"></p>
					<span id="sol-search-status-template" hidden><?php esc_html_e( 'Search matches: organisations {organisations}, units {units}, SOL roles {roles}.', 'scouting-openid-connect' ); ?></span>
					<span id="sol-search-result-template" hidden><?php esc_html_e( '{count} matches. Current selection: {selection}.', 'scouting-openid-connect' ); ?></span>
					<h3><?php esc_html_e( 'Then', 'scouting-openid-connect' ); ?></h3>
					<table class="form-table" role="presentation">
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
						<?php submit_button( $editing ? __( 'Save Rule', 'scouting-openid-connect' ) : __( 'Add Rule', 'scouting-openid-connect' ), 'primary', 'submit', false ); ?>
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
			<p id="<?php echo esc_attr( $selector_id . '-results' ); ?>" class="description" role="status" aria-atomic="true"></p>
		</div>
		<?php
	}

	/**
	 * Saves a mapping rule after capability, nonce and field validation.
	 *
	 * @since 2.7.0 Saves ordered SOL mapping rules.
	 */
	public function scouting_oidc_roles_admin_save_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SOL role mappings.', 'scouting-openid-connect' ) );
		}
		check_admin_referer( 'scouting_oidc_roles_save_rule' );
		$mapping  = $this->scouting_oidc_roles_admin_get_mapping();
		$original = $mapping;
		$rule_id  = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$rule     = $this->scouting_oidc_roles_admin_validate_rule( $_POST );
		if ( is_wp_error( $rule ) ) {
			$this->scouting_oidc_roles_admin_redirect( 'sol_hierarchy_read_failed' === $rule->get_error_code() ? 'save-failed' : 'invalid' );
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

		if ( $original !== $mapping && ! update_option( self::RULES_OPTION, $mapping, false ) ) {
			$this->scouting_oidc_roles_admin_redirect( 'save-failed' );
		}
		$this->scouting_oidc_roles_admin_redirect( 'saved' );
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
		$rule_id          = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$mapping          = $this->scouting_oidc_roles_admin_get_mapping();
		$original         = $mapping;
		$mapping['rules'] = array_values(
			array_filter(
				$mapping['rules'],
				static fn( array $rule ): bool => $rule['id'] !== $rule_id
			)
		);
		if ( $original !== $mapping && ! update_option( self::RULES_OPTION, $mapping, false ) ) {
			$this->scouting_oidc_roles_admin_redirect( 'save-failed' );
		}
		$this->scouting_oidc_roles_admin_redirect( 'deleted' );
	}

	/**
	 * Moves a mapping rule up or down one position.
	 *
	 * @since 2.7.0 Reorders SOL mapping rules.
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
		if ( false === $index ) {
			$this->scouting_oidc_roles_admin_redirect( 'invalid' );
		}
		if ( isset( $_POST['position'] ) ) {
			$original = $mapping;
			$position = filter_var( wp_unslash( $_POST['position'] ), FILTER_VALIDATE_INT );
			if ( false === $position || $position < 1 || $position > count( $mapping['rules'] ) ) {
				$this->scouting_oidc_roles_admin_redirect( 'invalid' );
			}
			$rule = $mapping['rules'][ $index ];
			array_splice( $mapping['rules'], $index, 1 );
			array_splice( $mapping['rules'], $position - 1, 0, array( $rule ) );
			if ( $original !== $mapping && ! update_option( self::RULES_OPTION, $mapping, false ) ) {
				$this->scouting_oidc_roles_admin_redirect( 'save-failed' );
			}
			$this->scouting_oidc_roles_admin_redirect( 'saved' );
		}
		$offset = 0;
		if ( 'up' === $direction ) {
			$offset = -1;
		} elseif ( 'down' === $direction ) {
			$offset = 1;
		}
		$target = $index + $offset;
		if ( 0 === $offset || ! isset( $mapping['rules'][ $target ] ) ) {
			$this->scouting_oidc_roles_admin_redirect( 'invalid' );
		}
		$current_rule                = $mapping['rules'][ $index ];
		$mapping['rules'][ $index ]  = $mapping['rules'][ $target ];
		$mapping['rules'][ $target ] = $current_rule;
		if ( ! update_option( self::RULES_OPTION, $mapping, false ) ) {
			$this->scouting_oidc_roles_admin_redirect( 'save-failed' );
		}

		$this->scouting_oidc_roles_admin_redirect( 'saved' );
	}

	/**
	 * Copies a saved rule to the end so existing precedence is preserved.
	 *
	 * @since 2.7.0 Adds rule duplication.
	 */
	public function scouting_oidc_roles_admin_duplicate_rule(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage SOL role mappings.', 'scouting-openid-connect' ) );
		}
		check_admin_referer( 'scouting_oidc_roles_duplicate_rule' );
		$rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( wp_unslash( $_POST['rule_id'] ) ) : '';
		$mapping = $this->scouting_oidc_roles_admin_get_mapping();
		foreach ( $mapping['rules'] as $rule ) {
			if ( $rule['id'] === $rule_id ) {
				$rule['id']         = wp_generate_uuid4();
				$mapping['rules'][] = $rule;
				if ( ! update_option( self::RULES_OPTION, $mapping, false ) ) {
					$this->scouting_oidc_roles_admin_redirect( 'save-failed' );
				}
				wp_safe_redirect(
					wp_nonce_url(
						add_query_arg(
							array(
								'page'   => 'scouting-oidc-role-mapping',
								'edit'   => $rule['id'],
								'notice' => 'duplicated',
							),
							admin_url( 'admin.php' )
						),
						'scouting_oidc_roles_edit_rule'
					) . '#scouting-oidc-rule-editor'
				);
				exit;
			}
		}
		$this->scouting_oidc_roles_admin_redirect( 'invalid' );
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
		$name = $wordpress_roles[ $rule['wordpress_role'] ]['name'] ?? $rule['wordpress_role'];
		/* translators: %s: WordPress role name. */
		return sprintf( __( 'Allow login; assign %s', 'scouting-openid-connect' ), translate_user_role( $name ) );
	}

	/**
	 * Tests a saved mapping against a user's synchronized active assignments.
	 *
	 * @since 2.7.0 Adds a read-only authorization tester with a progressive modal.
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
			<div id="sol-user-test-result" tabindex="-1">
			<?php if ( '' !== $error ) : ?>
				<p id="sol-user-test-error" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php elseif ( $user ) : ?>
				<?php
				$preview = RolesAuthorization::scouting_oidc_roles_authorization_get_user_preview( $user->ID );
				?>
				<?php if ( is_wp_error( $preview ) ) : ?>
					<p role="alert"><?php echo esc_html( 'sol_assignments_unavailable' === $preview->get_error_code() ? $preview->get_error_message() : __( 'The preview could not read SOL assignments. Check the database before relying on a login decision.', 'scouting-openid-connect' ) ); ?></p>
				<?php else : ?>
					<h3><?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?></h3>
					<p>
						<?php
						/* translators: %d: winning rule position. */
						echo esc_html( null === $preview['rule_index'] ? __( 'No matching rule: use the WordPress default role. Other login and new-user registration checks still apply.', 'scouting-openid-connect' ) : sprintf( __( 'Winning rule: %d', 'scouting-openid-connect' ), $preview['rule_index'] + 1 ) );
						?>
						<br /><?php echo esc_html( $this->scouting_oidc_roles_admin_describe_outcome( $preview['decision'], $wordpress_roles ) ); ?>
					</p>
					<h4><?php esc_html_e( 'Active SOL assignments', 'scouting-openid-connect' ); ?></h4>
					<?php if ( empty( $preview['assignments'] ) ) : ?>
						<p><?php esc_html_e( 'No stored active SOL assignments.', 'scouting-openid-connect' ); ?></p>
					<?php else : ?>
						<ul>
						<?php foreach ( $preview['assignments'] as $assignment ) : ?>
							<li><?php echo esc_html( $this->scouting_oidc_roles_admin_describe_conditions( $assignment, $organisations, $units, $roles ) ); ?></li>
						<?php endforeach; ?>
						</ul>
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
		$organisation_id    = isset( $input['organisation_id'] ) ? sanitize_text_field( wp_unslash( $input['organisation_id'] ) ) : '';
		$unit_id            = isset( $input['organisation_unit_id'] ) ? absint( $input['organisation_unit_id'] ) : 0;
		$role_id            = isset( $input['role_id'] ) ? absint( $input['role_id'] ) : 0;
		$wordpress_role     = isset( $input['wordpress_role'] ) ? sanitize_key( wp_unslash( $input['wordpress_role'] ) ) : '';
		$login_allowed      = isset( $input['login_allowed'] ) ? sanitize_text_field( wp_unslash( $input['login_allowed'] ) ) : '';
		$wordpress_roles    = wp_roles()->roles;
		$units              = $this->scouting_oidc_roles_admin_get_organisation_units();
		$roles              = $this->scouting_oidc_roles_admin_get_roles();
		$organisation_paths = RolesAuthorization::scouting_oidc_roles_authorization_get_organisation_paths( array_map( 'strval', array_column( $units, 'organisation_id' ) ) );
		if ( is_wp_error( $organisation_paths ) ) {
			return $organisation_paths;
		}

		if ( '' !== $organisation_id && ! isset( $this->scouting_oidc_roles_admin_get_organisations()[ $organisation_id ] ) ) {
			return new \WP_Error( 'invalid_organisation' );
		}
		if ( $unit_id > 0 && ! isset( $units[ $unit_id ] ) ) {
			return new \WP_Error( 'invalid_organisation_unit' );
		}
		if ( $role_id > 0 && ! isset( $roles[ $role_id ] ) ) {
			return new \WP_Error( 'invalid_role' );
		}
		if ( $unit_id > 0 && '' !== $organisation_id && ! in_array( $organisation_id, $organisation_paths[ $units[ $unit_id ]['organisation_id'] ] ?? array(), true ) ) {
			return new \WP_Error( 'invalid_rule' );
		}
		if ( $role_id > 0 && $unit_id > 0 && (int) $roles[ $role_id ]['organisation_unit_id'] !== $unit_id ) {
			return new \WP_Error( 'invalid_rule' );
		}
		if ( $role_id > 0 && '' !== $organisation_id && ! in_array( $organisation_id, $organisation_paths[ $roles[ $role_id ]['organisation_id'] ] ?? array(), true ) ) {
			return new \WP_Error( 'invalid_rule' );
		}
		if ( ! isset( $wordpress_roles[ $wordpress_role ] ) || ! in_array( $login_allowed, array( '0', '1' ), true ) || ( '' === $organisation_id && 0 === $unit_id && 0 === $role_id ) ) {
			return new \WP_Error( 'invalid_rule' );
		}

		return array(
			'id'                   => '',
			'organisation_id'      => $organisation_id,
			'organisation_unit_id' => $unit_id,
			'role_id'              => $role_id,
			'wordpress_role'       => $wordpress_role,
			'login_allowed'        => '1' === $login_allowed,
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
	 * @since 2.7.0 Reports role administration actions.
	 */
	private function scouting_oidc_roles_admin_render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This value only selects a fixed, escaped status message.
		$message  = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
		$messages = array(
			'save-failed' => __( 'The role mapping could not be saved. Please try again or check the database.', 'scouting-openid-connect' ),
			'duplicated'  => __( 'Rule duplicated at the end. Edit its conditions or outcome, then move it if needed.', 'scouting-openid-connect' ),
			'saved'       => __( 'Role mapping saved.', 'scouting-openid-connect' ),
			'deleted'     => __( 'Role mapping deleted.', 'scouting-openid-connect' ),
			'invalid'     => __( 'The rule was invalid or referenced data that is no longer synchronized.', 'scouting-openid-connect' ),
		);
		if ( isset( $messages[ $message ] ) ) {
			$is_error = in_array( $message, array( 'invalid', 'save-failed' ), true );
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
