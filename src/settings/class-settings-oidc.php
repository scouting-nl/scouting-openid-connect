<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 1.0.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the OpenID Connect settings section, including rendering fields and
 * sanitizing input values.
 *
 * @since 1.0.0
 */
class Settings_Oidc {

	/**
	 * Registers OpenID Connect settings, sections, and fields.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_oidc(): void {
		// Add settings sections.
		add_settings_section(
			'scouting_oidc_settings',
			__( 'OpenID Connect Settings', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_oidc_callback' ),
			'scouting-openid-connect-settings'
		);

		// Add a settings text field.
		add_settings_field(
			'scouting_oidc_client_id',
			__( 'Client ID', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_oidc_client_id_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_settings'
		);

		// Add a settings text field.
		add_settings_field(
			'scouting_oidc_client_secret',
			__( 'Client Secret', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_oidc_client_secret_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_settings'
		);

		// Add a settings text field.
		add_settings_field(
			'scouting_oidc_scopes',
			__( 'Scopes', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_oidc_scopes_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_settings'
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_client_id',
			array(
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_client_secret',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_client_secret_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_scopes',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_scopes_option' ),
			)
		);
	}

	/**
	 * Renders section content.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_oidc_callback(): void {}

	/**
	 * Renders text field.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_oidc_client_id_callback(): void {
		$value = get_option( 'scouting_oidc_client_id' );
		echo '<input type="text" id="scouting_oidc_client_id" name="scouting_oidc_client_id" placeholder="' . esc_attr__( 'Client ID', 'scouting-openid-connect' ) . '" value="' . esc_attr( $value ) . '" size="55" required>';
	}

	/**
	 * Renders text field.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_oidc_client_secret_callback(): void {
		$has_secret = get_option( 'scouting_oidc_client_secret' ) !== '';
		$input_id   = 'scouting_oidc_client_secret';
		$toggle_id  = 'scouting_oidc_client_secret_toggle';
		$show_text  = __( 'Show', 'scouting-openid-connect' );
		$hide_text  = __( 'Hide', 'scouting-openid-connect' );

		echo '<input type="password" id="' . esc_attr( $input_id ) . '" name="scouting_oidc_client_secret" placeholder="' . esc_attr__( 'Enter new client secret', 'scouting-openid-connect' ) . '" value="" size="55"' . ( $has_secret ? '' : ' required' ) . ' />';
		echo ' <button type="button" class="button" id="' . esc_attr( $toggle_id ) . '" data-show-text="' . esc_attr( $show_text ) . '" data-hide-text="' . esc_attr( $hide_text ) . '" disabled>' . esc_html( $show_text ) . '</button>';
		if ( $has_secret ) {
			echo '<p class="description">' . esc_html__( 'A client secret is already stored. Leave this field empty to keep the current secret. For security reasons, the stored value cannot be shown here.', 'scouting-openid-connect' ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'No client secret stored yet.', 'scouting-openid-connect' ) . '</p>';
		}
	}

	/**
	 * Renders text field.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_oidc_scopes_callback(): void {
		$value = get_option( 'scouting_oidc_scopes' );
		echo '<input type="text" id="scouting_oidc_scopes" name="scouting_oidc_scopes" placeholder="' . esc_attr__( 'Scopes', 'scouting-openid-connect' ) . '" value="' . esc_attr( $value ) . '" size="55" required>';
	}

	/**
	 * Sanitizes client secret while preserving the existing value when blank.
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $input The input value.
	 * @return string String value.
	 */
	public function scouting_oidc_sanitize_client_secret_option( mixed $input ): string {
		$input    = is_string( $input ) ? trim( $input ) : '';
		$existing = get_option( 'scouting_oidc_client_secret', '' );
		$existing = is_string( $existing ) ? $existing : '';

		if ( '' === $input ) {
			if ( '' === $existing ) {
				add_settings_error(
					'scouting_oidc_client_secret',
					'scouting_oidc_client_secret_required',
					__( 'Client secret is required.', 'scouting-openid-connect' ),
					'error'
				);
			}

			return $existing;
		}

		return sanitize_text_field( $input );
	}

	/**
	 * Sanitizes scopes option to supported values only.
	 *
	 * Supported scopes: openid membership profile email address phone
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $input The input value.
	 * @return string String value.
	 */
	public function scouting_oidc_sanitize_scopes_option( mixed $input ): string {
		$allowed_scopes = array( 'openid', 'membership', 'profile', 'email', 'address', 'phone' );
		$input          = is_string( $input ) ? strtolower( trim( $input ) ) : '';

		// If the input is empty, return the default set of scopes.
		if ( '' === $input ) {
			return implode( ' ', $allowed_scopes );
		}

		// Replace commas and semicolons with spaces and show a warning.
		if ( preg_match( '/[,;]+/', $input ) === 1 ) {
			add_settings_error(
				'scouting_oidc_scopes',
				'scouting_oidc_scopes_format',
				__( 'Scopes should be separated by spaces. Commas and semicolons were converted automatically.', 'scouting-openid-connect' ),
				'warning'
			);

			$input = preg_replace( '/[,;]+/', ' ', $input ) ?? $input;
		}

		// Split by whitespace, remove duplicates and empty values.
		$parts = preg_split( '/\s+/', $input );
		$parts = $parts ? $parts : array();
		$parts = array_values( array_unique( array_filter( $parts, fn( $scope ) => '' !== $scope ) ) );

		// Identify unsupported scopes and show a warning if any were included.
		$unsupported_scopes = array_values( array_filter( $parts, fn( $scope ) => ! in_array( $scope, $allowed_scopes, true ) ) );
		if ( ! empty( $unsupported_scopes ) ) {
			$unsupported_scopes_safe = array_map( static fn( $scope ) => esc_html( sanitize_text_field( (string) $scope ) ), $unsupported_scopes );
			$allowed_scopes_safe     = array_map( static fn( $scope ) => esc_html( sanitize_text_field( (string) $scope ) ), $allowed_scopes );
			$unsupported_list        = implode( ', ', $unsupported_scopes_safe );
			$supported_list          = implode( ', ', $allowed_scopes_safe );
			$unsupported_message     = __( 'Unsupported scopes were removed:', 'scouting-openid-connect' ) . " {$unsupported_list}. " . __( 'Supported scopes are:', 'scouting-openid-connect' ) . " {$supported_list}.";

			add_settings_error(
				'scouting_oidc_scopes',
				'scouting_oidc_scopes_unsupported',
				$unsupported_message,
				'warning'
			);
		}

		// Keep only supported scopes and show a warning if unsupported scopes were included.
		$parts = array_values( array_filter( $parts, fn( $scope ) => in_array( $scope, $allowed_scopes, true ) ) );

		// If no valid scopes remain, return the default set.
		if ( empty( $parts ) ) {
			return implode( ' ', $allowed_scopes );
		}

		// Output in canonical order for consistency.
		$selected_set = array_fill_keys( $parts, true );
		$canonical    = array_values( array_filter( $allowed_scopes, fn( $scope ) => isset( $selected_set[ $scope ] ) ) );

		// Preserve the original input order in the warning message, but return in canonical order.
		return implode( ' ', $canonical );
	}
}
