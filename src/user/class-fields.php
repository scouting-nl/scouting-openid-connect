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
		?>
		<h2><?php esc_html_e( 'Scouts Online (SOL) Profile Information', 'scouting-openid-connect' ); ?></h2>

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
				<input type="date" name="birthdate" id="birthdate" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_birthdate', $user->ID ) ); ?>" class="regular-text" readonly/>
			</td>
		</tr>
		<?php
	}

	/**
	 * Displays the Gender field.
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
		?>
		<tr>
			<th><label for="gender"><?php esc_html_e( 'Gender', 'scouting-openid-connect' ); ?></label></th>
			<td>
				<select name="gender" id="gender" style="width: 15em; background-color: #f0f0f1;" aria-readonly="true">
					<option value="male" <?php selected( get_the_author_meta( 'scouting_oidc_gender', $user->ID ), 'male' ); ?>><?php esc_html_e( 'Male', 'scouting-openid-connect' ); ?></option>
					<option value="female" <?php selected( get_the_author_meta( 'scouting_oidc_gender', $user->ID ), 'female' ); ?>><?php esc_html_e( 'Female', 'scouting-openid-connect' ); ?></option>
					<option value="other" <?php selected( get_the_author_meta( 'scouting_oidc_gender', $user->ID ), 'other' ); ?>><?php esc_html_e( 'Other', 'scouting-openid-connect' ); ?></option>
					<option value="unknown" <?php selected( get_the_author_meta( 'scouting_oidc_gender', $user->ID ), 'unknown' ); ?>><?php esc_html_e( 'Unknown', 'scouting-openid-connect' ); ?></option>
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
				<input type="tel" name="phone_number" id="phone_number" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_phone_number', $user->ID ) ); ?>" class="regular-text" readonly/>
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
				<input type="text" name="street" id="street" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_street', $user->ID ) ); ?>" class="regular-text" readonly/>
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
				<input type="text" name="house_number" id="house_number" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_house_number', $user->ID ) ); ?>" class="regular-text" readonly/>
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
				<input type="text" name="postal_code" id="postal_code" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_postal_code', $user->ID ) ); ?>" class="regular-text" readonly/>
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
				<input type="text" name="locality" id="locality" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_locality', $user->ID ) ); ?>" class="regular-text" readonly/>
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
				<input type="text" name="country_code" id="country_code" value="<?php echo esc_attr( get_the_author_meta( 'scouting_oidc_country_code', $user->ID ) ); ?>" class="regular-text" readonly/>
			</td>
		</tr>
		<?php
	}
}
