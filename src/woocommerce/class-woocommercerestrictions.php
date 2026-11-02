<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.7.0 Adds WooCommerce SOL ordering restrictions.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restricts WooCommerce products to customers with matching SOL assignments.
 *
 * @since 2.7.0 Adds WooCommerce SOL ordering restrictions.
 */
class WooCommerceRestrictions {
	/**
	 * Product meta key for allowed SOL organisation IDs.
	 *
	 * @since 2.7.0 Stores WooCommerce organisation restrictions.
	 */
	private const ALLOWED_ORGANISATIONS_META_KEY = '_scouting_oidc_allowed_organisation_ids';

	/**
	 * Product meta key for allowed SOL role IDs.
	 *
	 * @since 2.7.0 Stores WooCommerce role restrictions.
	 */
	private const ALLOWED_ROLES_META_KEY = '_scouting_oidc_allowed_role_ids';

	/**
	 * Product meta key for denied SOL organisation IDs.
	 *
	 * @since 2.7.0 Stores WooCommerce organisation denials.
	 */
	private const DENIED_ORGANISATIONS_META_KEY = '_scouting_oidc_denied_organisation_ids';

	/**
	 * Product meta key for denied SOL role IDs.
	 *
	 * @since 2.7.0 Stores WooCommerce role denials.
	 */
	private const DENIED_ROLES_META_KEY = '_scouting_oidc_denied_role_ids';

	/**
	 * Product meta key controlling descendant organisation allow matches.
	 *
	 * @since 2.7.0 Supports direct organisation allow rules.
	 */
	private const ALLOWED_DESCENDANTS_META_KEY = '_scouting_oidc_allowed_include_descendants';

	/**
	 * Product meta key controlling descendant organisation deny matches.
	 *
	 * @since 2.7.0 Supports direct organisation deny rules.
	 */
	private const DENIED_DESCENDANTS_META_KEY = '_scouting_oidc_denied_include_descendants';

	/**
	 * Product meta key requiring both allow conditions on one assignment.
	 *
	 * @since 2.7.0 Supports combined organisation and role allow rules.
	 */
	private const ALLOWED_REQUIRE_BOTH_META_KEY = '_scouting_oidc_allowed_require_both';

	/**
	 * Product meta key requiring both deny conditions on one assignment.
	 *
	 * @since 2.7.0 Supports combined organisation and role deny rules.
	 */
	private const DENIED_REQUIRE_BOTH_META_KEY = '_scouting_oidc_denied_require_both';

	/**
	 * Registers WooCommerce hooks after active plugins have loaded.
	 *
	 * @since 2.7.0 Defers WooCommerce integration registration.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'scouting_oidc_woocommerce_restrictions_register_hooks' ), 20 );
	}

	/**
	 * Registers product-editor and purchasing hooks when WooCommerce is active.
	 *
	 * @since 2.7.0 Registers WooCommerce SOL ordering restrictions.
	 */
	public function scouting_oidc_woocommerce_restrictions_register_hooks(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		add_filter( 'woocommerce_product_data_tabs', array( $this, 'scouting_oidc_woocommerce_restrictions_add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'scouting_oidc_woocommerce_restrictions_render_product_data_panel' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'scouting_oidc_woocommerce_restrictions_enqueue_product_data_tab_style' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'scouting_oidc_woocommerce_restrictions_save_product_fields' ) );
		add_filter( 'woocommerce_is_purchasable', array( $this, 'scouting_oidc_woocommerce_restrictions_is_purchasable' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'scouting_oidc_woocommerce_restrictions_validate_add_to_cart' ), 10, 4 );
		add_action( 'woocommerce_check_cart_items', array( $this, 'scouting_oidc_woocommerce_restrictions_validate_cart' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'scouting_oidc_woocommerce_restrictions_render_purchase_notice' ), 6 );
	}

	/**
	 * Adds the SOL restrictions tab to WooCommerce Product data.
	 *
	 * @since 2.7.0 Adds WooCommerce SOL restriction product data tab.
	 *
	 * @param array<string, array> $tabs WooCommerce Product data tabs.
	 * @return array<string, array> WooCommerce Product data tabs.
	 */
	public function scouting_oidc_woocommerce_restrictions_add_product_data_tab( array $tabs ): array {
		$tabs['scouting_oidc'] = array(
			'label'    => __( 'SOL Access', 'scouting-openid-connect' ),
			'target'   => 'scouting_oidc_product_data',
			'class'    => array( 'scouting_oidc_tab' ),
			'priority' => 80,
		);

		return $tabs;
	}

	/**
	 * Enqueues SOL Product data tab and field styling for product editor screens.
	 *
	 * @since 2.7.0 Adds WooCommerce SOL restriction tab styling.
	 *
	 * @param string $hook_suffix Current WordPress admin page hook suffix.
	 */
	public function scouting_oidc_woocommerce_restrictions_enqueue_product_data_tab_style( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'scouting-oidc-woocommerce-restrictions',
			plugins_url( 'woocommerce-restrictions.css', __FILE__ ),
			array( 'woocommerce_admin_styles' ),
			(string) filemtime( __DIR__ . '/woocommerce-restrictions.css' )
		);
	}

	/**
	 * Renders the SOL restrictions panel in WooCommerce Product data.
	 *
	 * @since 2.7.0 Adds contextual role labels and configurable organisation and role matching.
	 */
	public function scouting_oidc_woocommerce_restrictions_render_product_data_panel(): void {
		global $post;

		$product_id               = $post instanceof \WP_Post ? $post->ID : 0;
		$allowed_organisation_ids = $this->scouting_oidc_woocommerce_restrictions_get_organisation_ids( $product_id );
		$allowed_role_ids         = $this->scouting_oidc_woocommerce_restrictions_get_role_ids( $product_id );
		$denied_organisation_ids  = $this->scouting_oidc_woocommerce_restrictions_get_organisation_ids( $product_id, self::DENIED_ORGANISATIONS_META_KEY );
		$denied_role_ids          = $this->scouting_oidc_woocommerce_restrictions_get_role_ids( $product_id, self::DENIED_ROLES_META_KEY );
		$organisations            = $this->scouting_oidc_woocommerce_restrictions_get_organisations();
		$roles                    = $this->scouting_oidc_woocommerce_restrictions_get_roles();
		$organisation_ids         = array_column( $organisations, 'organisation_id' );
		$role_ids                 = array_map( 'intval', array_column( $roles, 'role_id' ) );
		?>
		<div id="scouting_oidc_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<p id="scouting-oidc-allow-help" class="scouting-oidc-access-description"><?php esc_html_e( 'Select organisations or roles that may order this product. A customer needs one matching active organisation or role. Enable Require both organisation and role to match both fields on the same active assignment when both fields contain selections; with only one field selected, only that field is required. Include descendant organisations to match assignments in groups beneath a selected parent; turn it off to require a direct assignment in a selected organisation. Leave both allow fields empty to allow every customer unless a deny rule matches.', 'scouting-openid-connect' ); ?></p>
				<p class="form-field">
					<label for="scouting-oidc-allowed-organisations"><?php esc_html_e( 'Allowed SOL organisations', 'scouting-openid-connect' ); ?></label>
					<select id="scouting-oidc-allowed-organisations" class="wc-enhanced-select" name="scouting_oidc_allowed_organisation_ids[]" multiple="multiple" aria-describedby="scouting-oidc-allow-help" data-placeholder="<?php esc_attr_e( 'No organisation allow rule', 'scouting-openid-connect' ); ?>">
						<?php foreach ( $organisations as $organisation ) : ?>
							<option value="<?php echo esc_attr( $organisation['organisation_id'] ); ?>" <?php selected( in_array( $organisation['organisation_id'], $allowed_organisation_ids, true ) ); ?>><?php echo esc_html( $organisation['name'] . ' (' . $organisation['organisation_id'] . ')' ); ?></option>
						<?php endforeach; ?>
						<?php foreach ( array_diff( $allowed_organisation_ids, $organisation_ids ) as $organisation_id ) : ?>
							<?php
							/* translators: %s: organisation ID. */
							$unavailable_organisation_label = sprintf( __( '%s (no longer synchronized)', 'scouting-openid-connect' ), $organisation_id );
							?>
							<option value="<?php echo esc_attr( $organisation_id ); ?>" selected="selected"><?php echo esc_html( $unavailable_organisation_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php $this->scouting_oidc_woocommerce_restrictions_render_checkbox( 'scouting_oidc_allowed_include_descendants', 'scouting-oidc-allow-help', __( 'Include descendant organisations', 'scouting-openid-connect' ), $this->scouting_oidc_woocommerce_restrictions_include_descendants( $product_id, self::ALLOWED_DESCENDANTS_META_KEY ) ); ?>
				<p class="form-field">
					<label for="scouting-oidc-allowed-roles"><?php esc_html_e( 'Allowed SOL roles', 'scouting-openid-connect' ); ?></label>
					<select id="scouting-oidc-allowed-roles" class="wc-enhanced-select" name="scouting_oidc_allowed_role_ids[]" multiple="multiple" aria-describedby="scouting-oidc-allow-help" data-placeholder="<?php esc_attr_e( 'No role allow rule', 'scouting-openid-connect' ); ?>">
						<?php foreach ( $roles as $role ) : ?>
							<option value="<?php echo esc_attr( (string) $role['role_id'] ); ?>" <?php selected( in_array( (int) $role['role_id'], $allowed_role_ids, true ) ); ?>><?php echo esc_html( $this->scouting_oidc_woocommerce_restrictions_describe_role( $role ) ); ?></option>
						<?php endforeach; ?>
						<?php foreach ( array_diff( $allowed_role_ids, $role_ids ) as $role_id ) : ?>
							<?php
							/* translators: %d: role ID. */
							$unavailable_role_label = sprintf( __( '%d (no longer synchronized)', 'scouting-openid-connect' ), $role_id );
							?>
							<option value="<?php echo esc_attr( (string) $role_id ); ?>" selected="selected"><?php echo esc_html( $unavailable_role_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php $this->scouting_oidc_woocommerce_restrictions_render_checkbox( 'scouting_oidc_allowed_require_both', 'scouting-oidc-allow-help', __( 'Require both organisation and role', 'scouting-openid-connect' ), 'yes' === get_post_meta( $product_id, self::ALLOWED_REQUIRE_BOTH_META_KEY, true ) ); ?>
			</div>
			<div class="options_group">
				<p id="scouting-oidc-deny-help" class="scouting-oidc-access-description"><?php esc_html_e( 'Select organisations or roles that may not order this product. Enable Require both organisation and role to match both fields on the same active assignment when both fields contain selections; with only one field selected, only that field is required. Include descendant organisations to deny assignments in groups beneath a selected parent; turn it off to deny only direct assignments in selected organisations. Any matching deny rule blocks ordering, even when the customer also matches an allow rule.', 'scouting-openid-connect' ); ?></p>
				<p class="form-field">
					<label for="scouting-oidc-denied-organisations"><?php esc_html_e( 'Denied SOL organisations', 'scouting-openid-connect' ); ?></label>
					<select id="scouting-oidc-denied-organisations" class="wc-enhanced-select" name="scouting_oidc_denied_organisation_ids[]" multiple="multiple" aria-describedby="scouting-oidc-deny-help" data-placeholder="<?php esc_attr_e( 'No organisation deny rule', 'scouting-openid-connect' ); ?>">
						<?php foreach ( $organisations as $organisation ) : ?>
							<option value="<?php echo esc_attr( $organisation['organisation_id'] ); ?>" <?php selected( in_array( $organisation['organisation_id'], $denied_organisation_ids, true ) ); ?>><?php echo esc_html( $organisation['name'] . ' (' . $organisation['organisation_id'] . ')' ); ?></option>
						<?php endforeach; ?>
						<?php foreach ( array_diff( $denied_organisation_ids, $organisation_ids ) as $organisation_id ) : ?>
							<?php
							/* translators: %s: organisation ID. */
							$unavailable_organisation_label = sprintf( __( '%s (no longer synchronized)', 'scouting-openid-connect' ), $organisation_id );
							?>
							<option value="<?php echo esc_attr( $organisation_id ); ?>" selected="selected"><?php echo esc_html( $unavailable_organisation_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php $this->scouting_oidc_woocommerce_restrictions_render_checkbox( 'scouting_oidc_denied_include_descendants', 'scouting-oidc-deny-help', __( 'Include descendant organisations', 'scouting-openid-connect' ), $this->scouting_oidc_woocommerce_restrictions_include_descendants( $product_id, self::DENIED_DESCENDANTS_META_KEY ) ); ?>
				<p class="form-field">
					<label for="scouting-oidc-denied-roles"><?php esc_html_e( 'Denied SOL roles', 'scouting-openid-connect' ); ?></label>
					<select id="scouting-oidc-denied-roles" class="wc-enhanced-select" name="scouting_oidc_denied_role_ids[]" multiple="multiple" aria-describedby="scouting-oidc-deny-help" data-placeholder="<?php esc_attr_e( 'No role deny rule', 'scouting-openid-connect' ); ?>">
						<?php foreach ( $roles as $role ) : ?>
							<option value="<?php echo esc_attr( (string) $role['role_id'] ); ?>" <?php selected( in_array( (int) $role['role_id'], $denied_role_ids, true ) ); ?>><?php echo esc_html( $this->scouting_oidc_woocommerce_restrictions_describe_role( $role ) ); ?></option>
						<?php endforeach; ?>
						<?php foreach ( array_diff( $denied_role_ids, $role_ids ) as $role_id ) : ?>
							<?php
							/* translators: %d: role ID. */
							$unavailable_role_label = sprintf( __( '%d (no longer synchronized)', 'scouting-openid-connect' ), $role_id );
							?>
							<option value="<?php echo esc_attr( (string) $role_id ); ?>" selected="selected"><?php echo esc_html( $unavailable_role_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php $this->scouting_oidc_woocommerce_restrictions_render_checkbox( 'scouting_oidc_denied_require_both', 'scouting-oidc-deny-help', __( 'Require both organisation and role', 'scouting-openid-connect' ), 'yes' === get_post_meta( $product_id, self::DENIED_REQUIRE_BOTH_META_KEY, true ) ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a native checkbox and marker distinguishing absent legacy forms.
	 *
	 * @since 2.7.0 Renders labelled organisation and role matching controls.
	 *
	 * @param string $name Checkbox field name.
	 * @param string $description_id Instructions ID.
	 * @param string $label Translated checkbox label.
	 * @param bool   $is_checked Whether the checkbox is selected.
	 */
	private function scouting_oidc_woocommerce_restrictions_render_checkbox( string $name, string $description_id, string $label, bool $is_checked ): void {
		?>
		<p class="form-field">
			<input type="hidden" name="<?php echo esc_attr( $name . '_present' ); ?>" value="1" />
			<label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="yes" aria-describedby="<?php echo esc_attr( $description_id ); ?>" <?php checked( $is_checked ); ?> />
		</p>
		<?php
	}

	/**
	 * Preserves descendant matching for products without an explicit opt-out.
	 *
	 * @since 2.7.0 Defaults existing products to descendant matching.
	 *
	 * @param int    $product_id Restriction owner product ID.
	 * @param string $meta_key Descendant matching meta key.
	 * @return bool Whether to include descendants.
	 */
	private function scouting_oidc_woocommerce_restrictions_include_descendants( int $product_id, string $meta_key ): bool {
		return 'no' !== get_post_meta( $product_id, $meta_key, true );
	}

	/**
	 * Saves SOL ordering restrictions through the WooCommerce product data API.
	 *
	 * @since 2.7.0 Saves WooCommerce SOL restriction fields and matching modes.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public function scouting_oidc_woocommerce_restrictions_save_product_fields( $product ): void {
		if ( ! $product instanceof \WC_Product || ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		$organisation_ids        = isset( $_POST['scouting_oidc_allowed_organisation_ids'] ) && is_array( $_POST['scouting_oidc_allowed_organisation_ids'] ) ? $this->scouting_oidc_woocommerce_restrictions_normalize_organisation_ids( array_map( 'sanitize_text_field', wp_unslash( $_POST['scouting_oidc_allowed_organisation_ids'] ) ) ) : array();
		$role_ids                = isset( $_POST['scouting_oidc_allowed_role_ids'] ) && is_array( $_POST['scouting_oidc_allowed_role_ids'] ) ? $this->scouting_oidc_woocommerce_restrictions_normalize_role_ids( array_map( 'sanitize_text_field', wp_unslash( $_POST['scouting_oidc_allowed_role_ids'] ) ) ) : array();
		$denied_organisation_ids = isset( $_POST['scouting_oidc_denied_organisation_ids'] ) && is_array( $_POST['scouting_oidc_denied_organisation_ids'] ) ? $this->scouting_oidc_woocommerce_restrictions_normalize_organisation_ids( array_map( 'sanitize_text_field', wp_unslash( $_POST['scouting_oidc_denied_organisation_ids'] ) ) ) : array();
		$denied_role_ids         = isset( $_POST['scouting_oidc_denied_role_ids'] ) && is_array( $_POST['scouting_oidc_denied_role_ids'] ) ? $this->scouting_oidc_woocommerce_restrictions_normalize_role_ids( array_map( 'sanitize_text_field', wp_unslash( $_POST['scouting_oidc_denied_role_ids'] ) ) ) : array();

		$this->scouting_oidc_woocommerce_restrictions_update_product_meta( $product, self::ALLOWED_ORGANISATIONS_META_KEY, $organisation_ids );
		$this->scouting_oidc_woocommerce_restrictions_update_product_meta( $product, self::ALLOWED_ROLES_META_KEY, $role_ids );
		$this->scouting_oidc_woocommerce_restrictions_update_product_meta( $product, self::DENIED_ORGANISATIONS_META_KEY, $denied_organisation_ids );
		$this->scouting_oidc_woocommerce_restrictions_update_product_meta( $product, self::DENIED_ROLES_META_KEY, $denied_role_ids );
		if ( isset( $_POST['scouting_oidc_allowed_include_descendants_present'] ) ) {
			$product->update_meta_data( self::ALLOWED_DESCENDANTS_META_KEY, isset( $_POST['scouting_oidc_allowed_include_descendants'] ) ? 'yes' : 'no' );
		}
		if ( isset( $_POST['scouting_oidc_denied_include_descendants_present'] ) ) {
			$product->update_meta_data( self::DENIED_DESCENDANTS_META_KEY, isset( $_POST['scouting_oidc_denied_include_descendants'] ) ? 'yes' : 'no' );
		}
		if ( isset( $_POST['scouting_oidc_allowed_require_both_present'] ) ) {
			$product->update_meta_data( self::ALLOWED_REQUIRE_BOTH_META_KEY, isset( $_POST['scouting_oidc_allowed_require_both'] ) ? 'yes' : 'no' );
		}
		if ( isset( $_POST['scouting_oidc_denied_require_both_present'] ) ) {
			$product->update_meta_data( self::DENIED_REQUIRE_BOTH_META_KEY, isset( $_POST['scouting_oidc_denied_require_both'] ) ? 'yes' : 'no' );
		}
	}

	/**
	 * Makes restricted products unavailable to unauthorised customers.
	 *
	 * @since 2.7.0 Hides purchase controls for restricted products.
	 *
	 * @param bool        $purchasable Whether WooCommerce considers the product purchasable.
	 * @param \WC_Product $product Product being evaluated.
	 * @return bool Whether the current customer can purchase the product.
	 */
	public function scouting_oidc_woocommerce_restrictions_is_purchasable( bool $purchasable, $product ): bool {
		if ( ! $product instanceof \WC_Product || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $purchasable;
		}

		return $purchasable && $this->scouting_oidc_woocommerce_restrictions_current_user_can_order_product( $product->get_id() );
	}

	/**
	 * Rejects attempts to add an unauthorised product or booking to the cart.
	 *
	 * @since 2.7.0 Validates SOL restrictions before adding cart items.
	 *
	 * @param bool $passed Whether previous validation passed.
	 * @param int  $product_id Product ID.
	 * @param int  $_quantity Requested quantity.
	 * @param int  $variation_id Variation ID when applicable.
	 * @return bool Whether the item may be added to the cart.
	 */
	public function scouting_oidc_woocommerce_restrictions_validate_add_to_cart( bool $passed, int $product_id, int $_quantity, int $variation_id = 0 ): bool {
		if ( $_quantity < 1 || ! $passed || $this->scouting_oidc_woocommerce_restrictions_current_user_can_order_product( $variation_id > 0 ? $variation_id : $product_id ) ) {
			return $passed;
		}

		wc_add_notice( $this->scouting_oidc_woocommerce_restrictions_get_purchase_message(), 'error' );

		return false;
	}

	/**
	 * Revalidates restricted items already present in the cart.
	 *
	 * @since 2.7.0 Prevents checkout when SOL access changes.
	 */
	public function scouting_oidc_woocommerce_restrictions_validate_cart(): void {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$restricted_product_ids = array();
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = ! empty( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : (int) $cart_item['product_id'];
			if ( ! $this->scouting_oidc_woocommerce_restrictions_current_user_can_order_product( $product_id ) ) {
				$restricted_product_ids[] = $this->scouting_oidc_woocommerce_restrictions_get_restriction_product_id( $product_id );
			}
		}

		foreach ( array_unique( $restricted_product_ids ) as $product_id ) {
			wc_add_notice( $this->scouting_oidc_woocommerce_restrictions_get_purchase_message(), 'error' );
		}
	}

	/**
	 * Displays a clear availability message on restricted single product pages.
	 *
	 * @since 2.7.0 Explains unavailable restricted products.
	 */
	public function scouting_oidc_woocommerce_restrictions_render_purchase_notice(): void {
		global $product;

		if ( ! $product instanceof \WC_Product || $this->scouting_oidc_woocommerce_restrictions_current_user_can_order_product( $product->get_id() ) ) {
			return;
		}

		wc_print_notice( $this->scouting_oidc_woocommerce_restrictions_get_purchase_message(), 'notice' );
	}

	/**
	 * Checks whether the current user may order a product under its SOL rules.
	 *
	 * @since 2.7.0 Applies configurable organisation and role conditions to active assignments.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return bool Whether the current user can order the product.
	 */
	private function scouting_oidc_woocommerce_restrictions_current_user_can_order_product( int $product_id ): bool {
		$restriction_product_id  = $this->scouting_oidc_woocommerce_restrictions_get_restriction_product_id( $product_id );
		$organisation_ids        = $this->scouting_oidc_woocommerce_restrictions_get_organisation_ids( $restriction_product_id );
		$role_ids                = $this->scouting_oidc_woocommerce_restrictions_get_role_ids( $restriction_product_id );
		$denied_organisation_ids = $this->scouting_oidc_woocommerce_restrictions_get_organisation_ids( $restriction_product_id, self::DENIED_ORGANISATIONS_META_KEY );
		$denied_role_ids         = $this->scouting_oidc_woocommerce_restrictions_get_role_ids( $restriction_product_id, self::DENIED_ROLES_META_KEY );
		$has_allow_rule          = ! empty( $organisation_ids ) || ! empty( $role_ids );
		$can_order               = ! $has_allow_rule;
		$user_id                 = get_current_user_id();
		$allowed_descendants     = $this->scouting_oidc_woocommerce_restrictions_include_descendants( $restriction_product_id, self::ALLOWED_DESCENDANTS_META_KEY );
		$denied_descendants      = $this->scouting_oidc_woocommerce_restrictions_include_descendants( $restriction_product_id, self::DENIED_DESCENDANTS_META_KEY );
		$allowed_require_both    = 'yes' === get_post_meta( $restriction_product_id, self::ALLOWED_REQUIRE_BOTH_META_KEY, true );
		$denied_require_both     = 'yes' === get_post_meta( $restriction_product_id, self::DENIED_REQUIRE_BOTH_META_KEY, true );

		if ( $user_id > 0 ) {
			foreach ( RolesAuthorization::scouting_oidc_roles_authorization_get_active_assignments( $user_id ) as $assignment ) {
				$allowed_assignment_ids = $allowed_descendants ? $assignment['organisation_ids'] : array( $assignment['organisation_id'] );
				$denied_assignment_ids  = $denied_descendants ? $assignment['organisation_ids'] : array( $assignment['organisation_id'] );
				if ( $this->scouting_oidc_woocommerce_restrictions_assignment_matches( $denied_assignment_ids, $assignment['role_id'], $denied_organisation_ids, $denied_role_ids, $denied_require_both ) ) {
					return false;
				}

				if ( $this->scouting_oidc_woocommerce_restrictions_assignment_matches( $allowed_assignment_ids, $assignment['role_id'], $organisation_ids, $role_ids, $allowed_require_both ) ) {
					$can_order = true;
				}
			}
		}

		return $can_order;
	}

	/**
	 * Matches populated organisation and role conditions against one assignment.
	 *
	 * @since 2.7.0 Shares OR and same-assignment AND matching for allow and deny rules.
	 *
	 * @param array<string> $assignment_ids Organisation IDs eligible for this assignment.
	 * @param int           $assignment_role_id Active assignment role ID.
	 * @param array<string> $organisation_ids Selected organisation IDs.
	 * @param array<int>    $role_ids Selected role IDs.
	 * @param bool          $require_both Whether both populated fields must match.
	 * @return bool Whether the assignment matches the rule.
	 */
	private function scouting_oidc_woocommerce_restrictions_assignment_matches( array $assignment_ids, int $assignment_role_id, array $organisation_ids, array $role_ids, bool $require_both ): bool {
		$organisation_matches = ! empty( array_intersect( $assignment_ids, $organisation_ids ) );
		$role_matches         = in_array( $assignment_role_id, $role_ids, true );

		if ( $require_both && ! empty( $organisation_ids ) && ! empty( $role_ids ) ) {
			return $organisation_matches && $role_matches;
		}

		return $organisation_matches || $role_matches;
	}

	/**
	 * Gets the parent product that owns restrictions for a product or variation.
	 *
	 * @since 2.7.0 Shares restrictions between variable products and variations.
	 *
	 * @param int $product_id Product or variation ID.
	 * @return int Product ID that owns restriction metadata.
	 */
	private function scouting_oidc_woocommerce_restrictions_get_restriction_product_id( int $product_id ): int {
		$product = wc_get_product( $product_id );
		if ( $product instanceof \WC_Product && $product->is_type( 'variation' ) ) {
			return $product->get_parent_id();
		}

		return $product_id;
	}

	/**
	 * Gets saved SOL organisation IDs for a product restriction rule.
	 *
	 * @since 2.7.0 Reads WooCommerce organisation restriction rules.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $meta_key Product meta key.
	 * @return array<int, string> Organisation IDs.
	 */
	private function scouting_oidc_woocommerce_restrictions_get_organisation_ids( int $product_id, string $meta_key = self::ALLOWED_ORGANISATIONS_META_KEY ): array {
		return $this->scouting_oidc_woocommerce_restrictions_normalize_organisation_ids( get_post_meta( $product_id, $meta_key, true ) );
	}

	/**
	 * Gets saved SOL role IDs for a product restriction rule.
	 *
	 * @since 2.7.0 Reads WooCommerce role restriction rules.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $meta_key Product meta key.
	 * @return array<int, int> Role IDs.
	 */
	private function scouting_oidc_woocommerce_restrictions_get_role_ids( int $product_id, string $meta_key = self::ALLOWED_ROLES_META_KEY ): array {
		return $this->scouting_oidc_woocommerce_restrictions_normalize_role_ids( get_post_meta( $product_id, $meta_key, true ) );
	}

	/**
	 * Gets organisation choices for the WooCommerce product editor.
	 *
	 * @since 2.7.0 Lists SOL organisations for WooCommerce restrictions.
	 *
	 * @return array<int, array{organisation_id: string, name: string}> Organisations.
	 */
	private function scouting_oidc_woocommerce_restrictions_get_organisations(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'scouting_oidc_sol_organisations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT organisation_id, name FROM %i ORDER BY name, organisation_id', $table ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Gets role choices for the WooCommerce product editor.
	 *
	 * @since 2.7.0 Lists SOL roles with organisation and unit context.
	 *
	 * @return array<int, array{role_id: int|string, role_name: string, organisation_unit_id: int|string, unit_name: string|null, organisation_id: string|null, organisation_name: string|null}> Roles.
	 */
	private function scouting_oidc_woocommerce_restrictions_get_roles(): array {
		global $wpdb;

		$roles_table         = $wpdb->prefix . 'scouting_oidc_sol_roles';
		$units_table         = $wpdb->prefix . 'scouting_oidc_sol_organisation_units';
		$organisations_table = $wpdb->prefix . 'scouting_oidc_sol_organisations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT role.role_id, role.role_name, role.organisation_unit_id, unit.name AS unit_name, unit.organisation_id, organisation.name AS organisation_name FROM %i AS role LEFT JOIN %i AS unit ON unit.organisation_unit_id = role.organisation_unit_id LEFT JOIN %i AS organisation ON organisation.organisation_id = unit.organisation_id ORDER BY role.role_name, organisation.name, unit.name, role.role_id',
				$roles_table,
				$units_table,
				$organisations_table
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Distinguishes identically named roles by their organisation and unit.
	 *
	 * @since 2.7.0 Describes the assignment context in both role selectors.
	 *
	 * @param array $role Synchronized role with joined context.
	 * @return string Human-readable role choice.
	 */
	private function scouting_oidc_woocommerce_restrictions_describe_role( array $role ): string {
		$organisation = $role['organisation_name'] ?? $role['organisation_id'] ?? __( 'Unknown organisation', 'scouting-openid-connect' );
		$unit         = $role['unit_name'] ?? (string) $role['organisation_unit_id'];
		/* translators: 1: SOL role name, 2: organisation name or ID, 3: unit name or ID, 4: role ID. */
		return sprintf( __( '%1$s - %2$s / %3$s (%4$d)', 'scouting-openid-connect' ), $role['role_name'], $organisation, $unit, (int) $role['role_id'] );
	}

	/**
	 * Stores a normalized product metadata list through WooCommerce's data API.
	 *
	 * @since 2.7.0 Saves WooCommerce SOL restriction metadata.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @param string      $meta_key Product meta key.
	 * @param array       $values Normalized meta values.
	 */
	private function scouting_oidc_woocommerce_restrictions_update_product_meta( $product, string $meta_key, array $values ): void {
		if ( empty( $values ) ) {
			$product->delete_meta_data( $meta_key );
			return;
		}

		$product->update_meta_data( $meta_key, $values );
	}

	/**
	 * Normalizes organisation IDs while preserving their leading zeroes.
	 *
	 * @since 2.7.0 Validates WooCommerce SOL organisation restrictions.
	 *
	 * @param mixed $value Raw organisation IDs.
	 * @return array<int, string> Normalized organisation IDs.
	 */
	private function scouting_oidc_woocommerce_restrictions_normalize_organisation_ids( mixed $value ): array {
		$values = is_array( $value ) ? $value : array( $value );
		$result = array();
		foreach ( $values as $organisation_id ) {
			if ( is_string( $organisation_id ) && 1 === preg_match( '/^\d{6}$/', $organisation_id ) ) {
				$result[] = $organisation_id;
			}
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * Normalizes positive role IDs.
	 *
	 * @since 2.7.0 Validates WooCommerce SOL role restrictions.
	 *
	 * @param mixed $value Raw role IDs.
	 * @return array<int, int> Normalized role IDs.
	 */
	private function scouting_oidc_woocommerce_restrictions_normalize_role_ids( mixed $value ): array {
		$values = is_array( $value ) ? $value : array( $value );
		$result = array();
		foreach ( $values as $role_id ) {
			if ( is_scalar( $role_id ) ) {
				$role_id = absint( $role_id );
				if ( $role_id > 0 ) {
					$result[] = $role_id;
				}
			}
		}

		return array_values( array_unique( $result ) );
	}

	/**
	 * Gets the customer-facing message for unavailable restricted products.
	 *
	 * @since 2.7.0 Explains SOL ordering restrictions.
	 *
	 * @return string Restriction message.
	 */
	private function scouting_oidc_woocommerce_restrictions_get_purchase_message(): string {
		return __( 'Your current Scouts Online role or organisation does not permit ordering this product.', 'scouting-openid-connect' );
	}
}
