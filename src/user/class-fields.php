<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 1.0.0
 */

namespace ScoutingOIDC;

use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages Scouting OIDC user profile fields, including rendering read-only
 * profile values.
 *
 * @since 1.0.0
 */
class Fields {

	/**
	 * Authorized manual-role selections staged until WordPress saves the profile.
	 *
	 * @since 2.7.0 Separates validation from role persistence.
	 *
	 * @var array<int, string[]>
	 */
	private array $manual_role_updates = array();

	/**
	 * Adds the synchronized-role column beside WordPress's all-role display.
	 *
	 * @since 2.7.0 Distinguishes plugin-managed and independently assigned roles.
	 *
	 * @param array $columns Existing user columns.
	 * @return array Updated columns.
	 */
	public function scouting_oidc_fields_user_columns( array $columns ): array {
		$columns['scouting_oidc_roles'] = __( 'SOL-managed WordPress roles', 'scouting-openid-connect' );
		return $columns;
	}

	/**
	 * Displays all currently assigned roles owned by the plugin.
	 *
	 * @since 2.7.0 Shows multiple mapped roles in the Users list, excluding local accounts from SOL ownership lookup.
	 *
	 * @param string $output Existing column content.
	 * @param string $column Column identifier.
	 * @param int    $user_id WordPress user ID.
	 * @return string Escaped column content.
	 */
	public function scouting_oidc_fields_user_column( string $output, string $column, int $user_id ): string {
		if ( 'scouting_oidc_roles' !== $column ) {
			return $output;
		}
		if ( 'true' !== get_user_meta( $user_id, 'scouting_oidc_user', true ) ) {
			return esc_html__( 'None', 'scouting-openid-connect' );
		}
		$owned = RolesAuthorization::scouting_oidc_roles_authorization_get_owned_roles( $user_id );
		$user  = get_userdata( $user_id );
		if ( is_wp_error( $owned ) ) {
			return esc_html( $owned->get_error_message() );
		}
		if ( ! $user instanceof WP_User ) {
			return '';
		}
		$roles = wp_roles()->roles;
		$names = array_map( static fn( string $role ): string => translate_user_role( $roles[ $role ]['name'] ?? $role ), array_intersect( $owned, $user->roles ) );
		return empty( $names ) ? esc_html__( 'None', 'scouting-openid-connect' ) : esc_html( implode( ', ', $names ) );
	}

	/**
	 * Hides WordPress's single-role control where the multi-role editor replaces it.
	 *
	 * @since 2.7.0 Prevents the native selector from collapsing multiple SOL-managed roles while leaving local users' controls unchanged.
	 *
	 * @param string $hook Current administration page.
	 */
	public function scouting_oidc_fields_enqueue_role_style( string $hook ): void {
		if ( ! in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}
		$user_id = 'profile.php' === $hook ? get_current_user_id() : (int) filter_input( INPUT_GET, 'user_id', FILTER_VALIDATE_INT );
		if ( 'user-edit.php' === $hook && $user_id <= 0 ) {
			$user_id = (int) filter_input( INPUT_POST, 'user_id', FILTER_VALIDATE_INT );
		}
		if ( $user_id > 0 && current_user_can( 'edit_user', $user_id ) && 'true' === get_user_meta( $user_id, 'scouting_oidc_user', true ) ) {
			wp_add_inline_style( 'common', ".user-role-wrap {\n\tdisplay: none;\n}\n" );
		}
	}

	/**
	 * Shows synchronized roles and a separate multi-role editor for manual roles.
	 *
	 * @since 2.7.0 Supports multiple roles on profile and user-edit screens and shows assigned roles even when ownership metadata is invalid.
	 *
	 * @param WP_User $user User being displayed.
	 */
	public function scouting_oidc_fields_user_roles( WP_User $user ): void {
		if ( 'true' !== get_user_meta( $user->ID, 'scouting_oidc_user', true ) || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}
		$owned = RolesAuthorization::scouting_oidc_roles_authorization_get_owned_roles( $user->ID );
		?>
		<h2><?php esc_html_e( 'WordPress roles', 'scouting-openid-connect' ); ?></h2>
		<?php if ( is_wp_error( $owned ) ) : ?>
			<p role="alert"><?php echo esc_html( $owned->get_error_message() ); ?></p>
			<p><?php esc_html_e( 'Role ownership data is invalid. Assigned roles are shown below, but role editing is disabled to protect existing access.', 'scouting-openid-connect' ); ?></p>
			<p><strong><?php esc_html_e( 'Currently assigned WordPress roles', 'scouting-openid-connect' ); ?>:</strong>
				<?php
				$roles = wp_roles()->roles;
				$names = array_map( static fn( string $role ): string => translate_user_role( $roles[ $role ]['name'] ?? $role ), $user->roles );
				echo empty( $names ) ? esc_html__( 'None', 'scouting-openid-connect' ) : esc_html( implode( ', ', $names ) );
				?>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'SOL-managed roles are read-only here. Every matching allow rule contributes a role; obsolete plugin-granted roles are removed at the next successful OIDC login. Manual roles are preserved.', 'scouting-openid-connect' ); ?></p>
			<p><strong><?php esc_html_e( 'SOL-managed WordPress roles', 'scouting-openid-connect' ); ?>:</strong>
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The column callback returns escaped text.
				echo $this->scouting_oidc_fields_user_column( '', 'scouting_oidc_roles', $user->ID );
				?>
			</p>
			<?php if ( current_user_can( 'promote_user', $user->ID ) ) : ?>
				<?php wp_nonce_field( 'scouting_oidc_manual_roles_' . $user->ID, 'scouting_oidc_manual_roles_nonce' ); ?>
				<fieldset>
					<legend><?php esc_html_e( 'Independently assigned WordPress roles', 'scouting-openid-connect' ); ?></legend>
					<p class="description" id="scouting-oidc-manual-roles-help"><?php esc_html_e( 'Select any additional roles to manage manually. These roles are not removed by SOL synchronization. Unchecking a role removes only that manual assignment.', 'scouting-openid-connect' ); ?></p>
					<?php foreach ( get_editable_roles() as $slug => $role ) : ?>
						<?php if ( ! in_array( $slug, $owned, true ) ) : ?>
							<p><label><input type="checkbox" name="scouting_oidc_manual_roles[]" value="<?php echo esc_attr( $slug ); ?>" aria-describedby="scouting-oidc-manual-roles-help" <?php checked( in_array( $slug, $user->roles, true ) ); ?> /> <?php echo esc_html( translate_user_role( $role['name'] ) ); ?></label></p>
						<?php endif; ?>
					<?php endforeach; ?>
				</fieldset>
			<?php else : ?>
				<p><strong><?php esc_html_e( 'Independently assigned WordPress roles', 'scouting-openid-connect' ); ?>:</strong>
					<?php
					$roles = wp_roles()->roles;
					$names = array_map( static fn( string $role ): string => translate_user_role( $roles[ $role ]['name'] ?? $role ), array_diff( $user->roles, $owned ) );
					echo empty( $names ) ? esc_html__( 'None', 'scouting-openid-connect' ) : esc_html( implode( ', ', $names ) );
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Removes the native single-role submission before WordPress validates it.
	 *
	 * The core profile handlers have already verified their nonce before these
	 * hooks run. The dedicated role editor performs its own nonce checks.
	 *
	 * @since 2.7.0 Preserves multi-role assignments during native profile updates while leaving local users' role submissions unchanged.
	 *
	 * @param int $user_id User being updated.
	 */
	public function scouting_oidc_fields_preserve_roles( int $user_id ): void {
		if ( current_user_can( 'edit_user', $user_id ) && 'true' === get_user_meta( $user_id, 'scouting_oidc_user', true ) ) {
			unset( $_POST['role'] );
		}
	}

	/**
	 * Validates manual role changes and prevents native single-role replacement.
	 *
	 * @since 2.7.0 Preserves mapped roles during profile saves and restricts the custom role editor to explicitly marked OIDC accounts.
	 *
	 * @param \WP_Error $errors Profile validation errors.
	 * @param bool      $update Whether an existing account is being updated.
	 * @param object    $user Submitted user data.
	 */
	public function scouting_oidc_fields_validate_roles( \WP_Error $errors, bool $update, object $user ): void {
		if ( ! $update || 'true' !== get_user_meta( $user->ID, 'scouting_oidc_user', true ) ) {
			return;
		}
		unset( $user->role );
		unset( $this->manual_role_updates[ $user->ID ] );
		if ( ! isset( $_POST['scouting_oidc_manual_roles_nonce'] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user->ID ) || ! current_user_can( 'promote_user', $user->ID ) || ! is_string( $_POST['scouting_oidc_manual_roles_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scouting_oidc_manual_roles_nonce'] ) ), 'scouting_oidc_manual_roles_' . $user->ID ) ) {
			$errors->add( 'scouting_oidc_manual_roles_forbidden', __( 'You do not have permission to change these WordPress roles.', 'scouting-openid-connect' ) );
			return;
		}
		$owned    = RolesAuthorization::scouting_oidc_roles_authorization_get_owned_roles( $user->ID );
		$editable = get_editable_roles();
		if ( is_wp_error( $owned ) ) {
			$errors->add( $owned->get_error_code(), $owned->get_error_message() );
			return;
		}
		if ( isset( $_POST['scouting_oidc_manual_roles'] ) && ! is_array( $_POST['scouting_oidc_manual_roles'] ) ) {
			$errors->add( 'scouting_oidc_manual_roles_invalid', __( 'Select valid WordPress roles.', 'scouting-openid-connect' ) );
			return;
		}
		$submitted = isset( $_POST['scouting_oidc_manual_roles'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['scouting_oidc_manual_roles'] ) ) : array();
		foreach ( $submitted as $role ) {
			if ( ! is_string( $role ) || ! isset( $editable[ $role ] ) || in_array( $role, $owned, true ) ) {
				$errors->add( 'scouting_oidc_manual_roles_invalid', __( 'Select valid WordPress roles.', 'scouting-openid-connect' ) );
				return;
			}
		}
		if ( get_current_user_id() === (int) $user->ID && ! ( is_multisite() && current_user_can( 'manage_network_users' ) ) ) {
			$current_user = get_userdata( $user->ID );
			$roles_after  = $current_user instanceof WP_User ? array_merge( array_intersect( $current_user->roles, $owned ), array_diff( $current_user->roles, array_keys( $editable ) ), $submitted ) : array();
			$can_promote  = false;
			foreach ( $roles_after as $role ) {
				$registered_role = get_role( $role );
				if ( $registered_role && $registered_role->has_cap( 'promote_users' ) ) {
					$can_promote = true;
					break;
				}
			}
			if ( ! $can_promote ) {
				$errors->add( 'scouting_oidc_manual_roles_self_demotion', __( 'You cannot remove your own ability to manage user roles.', 'scouting-openid-connect' ) );
				return;
			}
		}
		$this->manual_role_updates[ $user->ID ] = array_values( array_unique( $submitted ) );
	}

	/**
	 * Saves validated manual assignments after WordPress successfully updates a user.
	 *
	 * @since 2.7.0 Changes manual roles without replacing synchronized roles.
	 *
	 * @param int $user_id Updated WordPress user ID.
	 */
	public function scouting_oidc_fields_save_roles( int $user_id ): void {
		if ( ! isset( $this->manual_role_updates[ $user_id ] ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) || ! current_user_can( 'promote_user', $user_id ) ) {
			wp_die( esc_html__( 'You do not have permission to change these WordPress roles.', 'scouting-openid-connect' ) );
		}
		$owned    = RolesAuthorization::scouting_oidc_roles_authorization_get_owned_roles( $user_id );
		$user     = get_userdata( $user_id );
		$editable = array_keys( get_editable_roles() );
		if ( is_wp_error( $owned ) || ! $user instanceof WP_User ) {
			wp_die( esc_html__( 'Your access level could not be updated. Please contact the site administrator.', 'scouting-openid-connect' ) );
		}
		$selected = $this->manual_role_updates[ $user_id ];
		unset( $this->manual_role_updates[ $user_id ] );
		$manual   = array_diff( $user->roles, $owned );
		$expected = array_unique( array_merge( array_intersect( $user->roles, $owned ), array_diff( $manual, $editable ), $selected ) );
		foreach ( array_diff( array_intersect( $manual, $editable ), $selected ) as $role ) {
			$user->remove_role( $role );
		}
		foreach ( $selected as $role ) {
			$user->add_role( $role );
		}
		$saved_user = get_userdata( $user_id );
		if ( ! $saved_user instanceof WP_User || array_diff( $expected, $saved_user->roles ) || array_diff( $saved_user->roles, $expected ) ) {
			Logger::error( LogComponent::ROLES, 'Manual WordPress role changes could not be saved.', $user_id );
			wp_die( esc_html__( 'Your access level could not be updated. Please contact the site administrator.', 'scouting-openid-connect' ) );
		}
	}

	/**
	 * Adds a link to the user's Scouts Online profile to the Users table.
	 *
	 * @since 2.5.0
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_User $user User represented by the row.
	 * @return array Updated row actions.
	 */
	public function scouting_oidc_fields_user_row_actions( array $actions, WP_User $user ): array {
		$sol_url = get_user_meta( $user->ID, 'scouting_oidc_sol_url', true );
		if ( ! wp_http_validate_url( $sol_url ) ) {
			return $actions;
		}

		$view_in_sol   = '<a href="' . esc_url( $sol_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View in SOL', 'scouting-openid-connect' ) . '</a>';
		$view_position = array_search( 'view', array_keys( $actions ), true );

		if ( false === $view_position ) {
			$actions['scouting_oidc_view_in_sol'] = $view_in_sol;
			return $actions;
		}

		return array_slice( $actions, 0, $view_position + 1, true )
			+ array( 'scouting_oidc_view_in_sol' => $view_in_sol )
			+ array_slice( $actions, $view_position + 1, null, true );
	}

	/**
	 * Displays user profile fields unless WooCommerce synchronization provides the
	 * address and phone fields.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Removed the separate SOL ID profile field.
	 * @since 2.2.0 Hid duplicate phone and address fields when WooCommerce is active.
	 * @since 2.4.0 Updated the parameter type.
	 *
	 * @param WP_User $user The user object.
	 */
	public function scouting_oidc_fields_user_profile( WP_User $user ): void {
		if ( 'true' !== get_user_meta( $user->ID, 'scouting_oidc_user', true ) ) {
			return;
		}
		?>
		<h2><?php esc_html_e( 'Scouts Online (SOL) Profile Information', 'scouting-openid-connect' ); ?></h2>
		<p class="description" id="scouting-oidc-profile-help"><?php esc_html_e( 'These values are managed in Scouts Online and refreshed when the plugin receives profile data during sign-in. Update your information there; changes are not synced continuously.', 'scouting-openid-connect' ); ?></p>

		<table class="form-table" role="presentation">
			<?php
			if ( get_option( 'scouting_oidc_user_birthdate' ) ) {
				$this->scouting_oidc_fields_birthdate( $user );
			}
			if ( get_option( 'scouting_oidc_user_gender' ) ) {
				$this->scouting_oidc_fields_gender( $user );
			}
			if ( get_option( 'scouting_oidc_user_phone' ) && ( ! get_option( 'scouting_oidc_user_woocommerce_sync' ) || ! class_exists( 'WooCommerce' ) ) ) {
				$this->scouting_oidc_fields_phone( $user );
			}
			if ( get_option( 'scouting_oidc_user_address' ) && ( ! get_option( 'scouting_oidc_user_woocommerce_sync' ) || ! class_exists( 'WooCommerce' ) ) ) {
				$this->scouting_oidc_fields_address_street( $user );
				$this->scouting_oidc_fields_address_house_number( $user );
				$this->scouting_oidc_fields_address_postal_code( $user );
				$this->scouting_oidc_fields_address_locality( $user );
				$this->scouting_oidc_fields_address_country_code( $user );
			}
			?>
		</table>
		<?php
	}

	/**
	 * Displays the Birthdate field.
	 *
	 * @since 1.0.0
	 * @since 2.4.0 Updated the parameter type.
	 *
	 * @param WP_User $user The user object.
	 */
	private function scouting_oidc_fields_birthdate( WP_User $user ): void {
		?>
		<tr>
			<th><label for="birthdate"><?php esc_html_e( 'Birthdate', 'scouting-openid-connect' ); ?></label></th>
			<td>
				<input type="date" name="birthdate" id="birthdate" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_birthdate', $user->ID ) ); ?>" class="regular-text" style="background-color: #fff;" aria-describedby="scouting-oidc-profile-help" readonly />
			</td>
		</tr>
		<?php
	}

	/**
	 * Displays the Gender dropdown with only the synchronized value enabled.
	 *
	 * @since 1.0.0
	 * @since 2.4.0 Updated the parameter type.
	 *
	 * @param WP_User $user The user object.
	 */
	private function scouting_oidc_fields_gender( WP_User $user ): void {
		if ( get_the_author_meta( 'scouting_oidc_gender', $user->ID ) === '' ) {
			update_user_meta( $user->ID, 'scouting_oidc_gender', 'unknown' );
		}
		$gender = get_the_author_meta( 'scouting_oidc_gender', $user->ID );
		?>
		<tr>
			<th><label for="gender"><?php esc_html_e( 'Gender', 'scouting-openid-connect' ); ?></label></th>
			<td>
				<select name="gender" id="gender" style="width: 15em; background-color: #fff;" aria-describedby="scouting-oidc-profile-help">
					<option value="male" <?php selected( $gender, 'male' ); ?> <?php disabled( 'male' !== $gender ); ?>><?php esc_html_e( 'Male', 'scouting-openid-connect' ); ?></option>
					<option value="female" <?php selected( $gender, 'female' ); ?> <?php disabled( 'female' !== $gender ); ?>><?php esc_html_e( 'Female', 'scouting-openid-connect' ); ?></option>
					<option value="other" <?php selected( $gender, 'other' ); ?> <?php disabled( 'other' !== $gender ); ?>><?php esc_html_e( 'Other', 'scouting-openid-connect' ); ?></option>
					<option value="unknown" <?php selected( $gender, 'unknown' ); ?> <?php disabled( 'unknown' !== $gender ); ?>><?php esc_html_e( 'Unknown', 'scouting-openid-connect' ); ?></option>
					<?php if ( ! in_array( $gender, array( 'male', 'female', 'other', 'unknown' ), true ) ) : ?>
						<option value="<?php echo esc_attr( $gender ); ?>" selected="selected"><?php echo esc_html( $gender ); ?></option>
					<?php endif; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Displays the Phone Number field.
	 *
	 * @since 2.2.0
	 * @since 2.4.0 Updated the parameter type.
	 *
	 * @param WP_User $user The user object.
	 */
	private function scouting_oidc_fields_phone( WP_User $user ): void {
		?>
		<tr>
			<th><label for="phone_number"><?php esc_html_e( 'Phone Number', 'scouting-openid-connect' ); ?></label></th>
			<td>
				<input type="tel" name="phone_number" id="phone_number" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_phone_number', $user->ID ) ); ?>" class="regular-text" aria-describedby="scouting-oidc-profile-help" readonly />
			</td>
		</tr>
		<?php
	}


	/**
	 * Displays the Street field.
	 *
	 * @since 2.2.0
	 * @since 2.4.0 Updated the parameter type.
	 *
	 * @param WP_User $user The user object.
	 */
	private function scouting_oidc_fields_address_street( WP_User $user ): void {
		?>
		<tr>
			<th><label for="street"><?php esc_html_e( 'Street', 'scouting-openid-connect' ); ?></label></th>
			<td>
				<input type="text" name="street" id="street" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_street', $user->ID ) ); ?>" class="regular-text" aria-describedby="scouting-oidc-profile-help" readonly />
			</td>
		</tr>
		<?php
	}

	/**
	 * Displays the House Number field.
	 *
	 * @since 2.2.0
	 * @since 2.4.0 Updated the parameter type.
	 *
	 * @param WP_User $user The user object.
	 */
	private function scouting_oidc_fields_address_house_number( WP_User $user ): void {
		?>
		<tr>
			<th><label for="house_number"><?php esc_html_e( 'House Number', 'scouting-openid-connect' ); ?></label></th>
			<td>
				<input type="text" name="house_number" id="house_number" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_house_number', $user->ID ) ); ?>" class="regular-text" aria-describedby="scouting-oidc-profile-help" readonly />
			</td>
		</tr>
		<?php
	}

	/**
	 * Displays the Postal Code field.
	 *
	 * @since 2.2.0
	 * @since 2.4.0 Updated the parameter type.
	 *
	 * @param WP_User $user The user object.
	 */
	private function scouting_oidc_fields_address_postal_code( WP_User $user ): void {
		?>
		<tr>
			<th><label for="postal_code"><?php esc_html_e( 'Postal Code', 'scouting-openid-connect' ); ?></label></th>
			<td>
				<input type="text" name="postal_code" id="postal_code" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_postal_code', $user->ID ) ); ?>" class="regular-text" aria-describedby="scouting-oidc-profile-help" readonly />
			</td>
		</tr>
		<?php
	}

	/**
	 * Displays the Locality field.
	 *
	 * @since 2.2.0
	 * @since 2.4.0 Updated the parameter type.
	 *
	 * @param WP_User $user The user object.
	 */
	private function scouting_oidc_fields_address_locality( WP_User $user ): void {
		?>
		<tr>
			<th><label for="locality"><?php esc_html_e( 'City', 'scouting-openid-connect' ); ?></label></th>
			<td>
				<input type="text" name="locality" id="locality" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_locality', $user->ID ) ); ?>" class="regular-text" aria-describedby="scouting-oidc-profile-help" readonly />
			</td>
		</tr>
		<?php
	}

	/**
	 * Displays the Country Code field.
	 *
	 * @since 2.2.0
	 * @since 2.4.0 Updated the parameter type.
	 *
	 * @param WP_User $user The user object.
	 */
	private function scouting_oidc_fields_address_country_code( WP_User $user ): void {
		?>
		<tr>
			<th><label for="country_code"><?php esc_html_e( 'Country Code', 'scouting-openid-connect' ); ?></label></th>
			<td>
				<input type="text" name="country_code" id="country_code" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_country_code', $user->ID ) ); ?>" class="regular-text" aria-describedby="scouting-oidc-profile-help" readonly />
			</td>
		</tr>
		<?php
	}
}
