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
 * Manages the general Scouting OIDC settings section, including rendering fields
 * and sanitizing input values.
 *
 * @since 1.0.0
 */
class Settings_General {

	/**
	 * Registers General settings, sections, and fields.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_general(): void {
		// Add settings sections.
		add_settings_section(
			'scouting_oidc_general_settings',
			__( 'General Settings', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_callback' ),
			'scouting-openid-connect-settings'
		);

		// Add a settings selectbox field.
		add_settings_field(
			'scouting_oidc_user_display_name',
			__( 'Set display name', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_display_name_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		// Add a settings checkbox field.
		add_settings_field(
			'scouting_oidc_user_birthdate',
			__( 'Store birthdate to local profile', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_birthdate_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		// Add a settings checkbox field.
		add_settings_field(
			'scouting_oidc_user_gender',
			__( 'Store gender to local profile', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_gender_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		// Add a settings checkbox field.
		add_settings_field(
			'scouting_oidc_user_phone',
			__( 'Store phone number to local profile', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_phone_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		// Add a settings checkbox field.
		add_settings_field(
			'scouting_oidc_user_address',
			__( 'Store address to local profile', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_address_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		if ( class_exists( 'WooCommerce' ) ) {
			// Add a settings checkbox field when WooCommerce is available.
			add_settings_field(
				'scouting_oidc_user_woocommerce_sync',
				__( 'Use WooCommerce phone number and address fields', 'scouting-openid-connect' ),
				array( $this, 'scouting_oidc_settings_general_woocommerce_sync_callback' ),
				'scouting-openid-connect-settings',
				'scouting_oidc_general_settings',
				array(
					'class' => 'scouting-oidc-user-woocommerce-sync-tr',
				)
			);
		}

		// Add a settings checkbox field.
		add_settings_field(
			'scouting_oidc_user_auto_create',
			__( 'Allow new user accounts', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_user_auto_create_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		// Add a settings selectbox field.
		add_settings_field(
			'scouting_oidc_user_duplicate_email',
			__( 'When an email already exists', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_duplicate_email_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		// Add a settings checkbox field.
		add_settings_field(
			'scouting_oidc_user_redirect',
			__( 'Redirect only SOL users', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_user_redirect_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		// Add a settings selectbox field.
		add_settings_field(
			'scouting_oidc_login_redirect',
			__( 'After a successful login redirect user to', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_login_redirect_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		// Add a settings text field.
		add_settings_field(
			'scouting_oidc_custom_redirect',
			__( 'Url to custom redirect after a successful login', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_custom_redirect_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings',
			array(
				'class' => 'scouting-oidc-custom-redirect-tr',
			)
		);

		// Add a settings checkbox field.
		add_settings_field(
			'scouting_oidc_debug_logging_enabled',
			__( 'Enable debug logs', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_debug_logging_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		// Add a settings number field.
		add_settings_field(
			'scouting_oidc_log_retention_days',
			__( 'Log retention (days)', 'scouting-openid-connect' ),
			array( $this, 'scouting_oidc_settings_general_log_retention_days_callback' ),
			'scouting-openid-connect-settings',
			'scouting_oidc_general_settings'
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_user_display_name',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_display_name_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_user_birthdate',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_boolean_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_user_gender',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_boolean_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_user_phone',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_boolean_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_user_address',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_boolean_option' ),
			)
		);

		if ( class_exists( 'WooCommerce' ) ) {
			// Register WooCommerce-specific setting only when available.
			register_setting(
				'scouting_oidc_settings_group',
				'scouting_oidc_user_woocommerce_sync',
				array(
					'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_boolean_option' ),
				)
			);
		}

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_user_auto_create',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_boolean_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_user_duplicate_email',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_duplicate_email_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_user_redirect',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_boolean_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_login_redirect',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_login_redirect_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_custom_redirect',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_custom_redirect_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_debug_logging_enabled',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_boolean_option' ),
			)
		);

		// Register settings.
		register_setting(
			'scouting_oidc_settings_group',
			'scouting_oidc_log_retention_days',
			array(
				'sanitize_callback' => array( $this, 'scouting_oidc_sanitize_log_retention_days_option' ),
			)
		);

		// Log settings changes for options that belong to this plugin.
		add_action( 'updated_option', array( $this, 'handle_option_update' ), 10, 3 );
	}

	/**
	 * Handles option updates and log changes for scouting_oidc_ options.
	 *
	 * @since 2.4.0
	 *
	 * @param string $option The option name.
	 * @param mixed  $old_value The previous option value.
	 * @param mixed  $value The value.
	 */
	public function handle_option_update( string $option, $old_value, $value ): void {
		if ( strpos( $option, 'scouting_oidc_' ) !== 0 ) {
			return;
		}

		$old = $this->format_setting_value( $old_value );
		$new = $this->format_setting_value( $value );

		if ( $old === $new ) {
			return;
		}

		// Redact sensitive options from logs.
		$sensitive_options = array( 'scouting_oidc_client_secret' );
		if ( in_array( $option, $sensitive_options, true ) ) {
			$old = '' !== $old ? '***REDACTED***' : 'null';
			$new = '' !== $new ? '***REDACTED***' : 'null';
		}

		Logger::info( LogComponent::SETTINGS, "Setting {$option} changed: {$old} -> {$new}" );
	}

	/**
	 * Converts a setting value to a short, safe string for logging.
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $value The value.
	 * @return string String value.
	 */
	public function format_setting_value( mixed $value ): string {
		if ( is_null( $value ) ) {
			return 'null';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		$json = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
		if ( false !== $json ) {
			return $json;
		}

		// Fallback for data that cannot be JSON encoded.
		return '[' . gettype( $value ) . ': Unable to encode]';
	}

	/**
	 * Sanitizes the display name option value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input The input value.
	 * @return string String value.
	 */
	public function scouting_oidc_sanitize_display_name_option( mixed $input ): string {
		// Define allowed options.
		$valid = array( 'fullname', 'firstname', 'lastname' );

		// Return the input if it’s a valid option; otherwise, default to 'fullname'.
		return in_array( $input, $valid, true ) ? $input : 'fullname';
	}

	/**
	 * Sanitizes the input value as a boolean.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input The input value.
	 * @return int Integer value.
	 */
	public function scouting_oidc_sanitize_boolean_option( mixed $input ): int {
		return $input ? 1 : 0;
	}

	/**
	 * Sanitizes the log retention option.
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $input The input value.
	 * @return int Integer value.
	 */
	public function scouting_oidc_sanitize_log_retention_days_option( mixed $input ): int {
		if ( ! is_numeric( $input ) ) {
			return 30;
		}

		$retention_days = (int) $input;

		if ( $retention_days < 1 ) {
			return 1;
		}

		if ( $retention_days > 3650 ) {
			return 3650;
		}

		return $retention_days;
	}

	/**
	 * Sanitizes the duplicate email option.
	 *
	 * @since 2.3.0
	 *
	 * @param mixed $input The input value.
	 * @return string String value.
	 */
	public function scouting_oidc_sanitize_duplicate_email_option( mixed $input ): string {
		// Define allowed options.
		$valid = array( 'plus_addressing', 'error' );

		// Return the input if it’s a valid option; otherwise, default to 'plus_addressing'.
		return in_array( $input, $valid, true ) ? $input : 'plus_addressing';
	}

	/**
	 * Sanitizes the login redirect option.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input The input value.
	 * @return string String value.
	 */
	public function scouting_oidc_sanitize_login_redirect_option( mixed $input ): string {
		// Define allowed options.
		$valid = array( 'default', 'frontpage', 'dashboard', 'custom' );

		// Return the input if it’s a valid option; otherwise, default to 'default'.
		return in_array( $input, $valid, true ) ? $input : 'default';
	}

	/**
	 * Sanitizes the custom redirect option.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $input The input value.
	 * @return string String value.
	 */
	public function scouting_oidc_sanitize_custom_redirect_option( mixed $input ): string {
		// Define your fixed base domain.
		$base_domain = home_url( '/' );

		// Add the base domain if it's not already present.
		if ( ! empty( $input ) && strpos( $input, $base_domain ) !== 0 ) {
			$input = $base_domain . ltrim( $input, '/' );
		}

		// Sanitize the input.
		return sanitize_text_field( $input );
	}

	/**
	 * Renders section content.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_general_callback(): void {}

	/**
	 * Renders selectbox field.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_general_display_name_callback(): void {
		$possible_values = array(
			'fullname'  => __( 'Full name', 'scouting-openid-connect' ),
			'firstname' => __( 'First name', 'scouting-openid-connect' ),
			'lastname'  => __( 'Last name', 'scouting-openid-connect' ),
		);
		$value           = get_option( 'scouting_oidc_user_display_name' );

		echo '<select id="scouting_oidc_user_display_name" name="scouting_oidc_user_display_name" style="width: 177px;">';
		foreach ( $possible_values as $key => $name ) {
			if ( $value === $key ) {
				echo '<option value="' . esc_attr( $key ) . '" selected>' . esc_html( $name ) . '</option>';
			} else {
				echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $name ) . '</option>';
			}
		}
		echo '</select>';
	}

	/**
	 * Renders birthdate checkbox field.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_general_birthdate_callback(): void {
		if ( get_option( 'scouting_oidc_user_birthdate' ) ) {
			echo '<input type="checkbox" id="scouting_oidc_user_birthdate" name="scouting_oidc_user_birthdate" checked/>';
		} else {
			echo '<input type="checkbox" id="scouting_oidc_user_birthdate" name="scouting_oidc_user_birthdate"/>';
		}
	}

	/**
	 * Renders gender checkbox field.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_general_gender_callback(): void {
		if ( get_option( 'scouting_oidc_user_gender' ) ) {
			echo '<input type="checkbox" id="scouting_oidc_user_gender" name="scouting_oidc_user_gender" checked/>';
		} else {
			echo '<input type="checkbox" id="scouting_oidc_user_gender" name="scouting_oidc_user_gender"/>';
		}
	}

	/**
	 * Renders phone checkbox field.
	 *
	 * @since 2.2.0
	 */
	public function scouting_oidc_settings_general_phone_callback(): void {
		if ( get_option( 'scouting_oidc_user_phone' ) ) {
			echo '<input type="checkbox" id="scouting_oidc_user_phone" name="scouting_oidc_user_phone" checked/>';
		} else {
			echo '<input type="checkbox" id="scouting_oidc_user_phone" name="scouting_oidc_user_phone"/>';
		}
	}

	/**
	 * Renders address checkbox field.
	 *
	 * @since 2.2.0
	 */
	public function scouting_oidc_settings_general_address_callback(): void {
		if ( get_option( 'scouting_oidc_user_address' ) ) {
			echo '<input type="checkbox" id="scouting_oidc_user_address" name="scouting_oidc_user_address" checked/>';
		} else {
			echo '<input type="checkbox" id="scouting_oidc_user_address" name="scouting_oidc_user_address"/>';
		}
	}

	/**
	 * Renders WooCommerce sync checkbox field.
	 *
	 * @since 2.2.0
	 */
	public function scouting_oidc_settings_general_woocommerce_sync_callback(): void {
		if ( get_option( 'scouting_oidc_user_woocommerce_sync' ) ) {
			echo '<input type="checkbox" id="scouting_oidc_user_woocommerce_sync" name="scouting_oidc_user_woocommerce_sync" checked/>';
		} else {
			echo '<input type="checkbox" id="scouting_oidc_user_woocommerce_sync" name="scouting_oidc_user_woocommerce_sync"/>';
		}
	}

	/**
	 * Renders user auto-create checkbox field.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_general_user_auto_create_callback(): void {
		if ( get_option( 'scouting_oidc_user_auto_create' ) ) {
			echo '<input type="checkbox" id="scouting_oidc_user_auto_create" name="scouting_oidc_user_auto_create" checked/>';
		} else {
			echo '<input type="checkbox" id="scouting_oidc_user_auto_create" name="scouting_oidc_user_auto_create"/>';
		}
	}

	/**
	 * Renders duplicate email selectbox field.
	 *
	 * @since 2.3.0
	 */
	public function scouting_oidc_settings_general_duplicate_email_callback(): void {
		$possible_values = array(
			'plus_addressing' => __( 'Add plus addressing to email', 'scouting-openid-connect' ),
			'error'           => __( 'Stop creation of user with an error', 'scouting-openid-connect' ),
		);
		$value           = get_option( 'scouting_oidc_user_duplicate_email', 'plus_addressing' );

		echo '<select id="scouting_oidc_user_duplicate_email" name="scouting_oidc_user_duplicate_email" style="width: 310px;">';
		foreach ( $possible_values as $key => $name ) {
			if ( $value === $key ) {
				echo '<option value="' . esc_attr( $key ) . '" selected>' . esc_html( $name ) . '</option>';
			} else {
				echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $name ) . '</option>';
			}
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'When a user tries to log in with an email that already exists in the system, this setting determines how to handle it. The default option is to add plus addressing.', 'scouting-openid-connect' ) . ' ' . esc_html( 'Example: local-part+sol_id@example.com' ) . '</p>';
	}

	/**
	 * Renders user redirect checkbox field.
	 *
	 * @since 1.1.0
	 */
	public function scouting_oidc_settings_general_user_redirect_callback(): void {
		if ( get_option( 'scouting_oidc_user_redirect' ) ) {
			echo '<input type="checkbox" id="scouting_oidc_user_redirect" name="scouting_oidc_user_redirect" checked/>';
		} else {
			echo '<input type="checkbox" id="scouting_oidc_user_redirect" name="scouting_oidc_user_redirect"/>';
		}
	}

	/**
	 * Renders login redirect selectbox field.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_general_login_redirect_callback(): void {
		$possible_values = array(
			'default'   => __( 'Default (no action)', 'scouting-openid-connect' ),
			'frontpage' => __( 'Frontpage', 'scouting-openid-connect' ),
			'dashboard' => __( 'Dashboard', 'scouting-openid-connect' ),
			'custom'    => __( 'Custom URL', 'scouting-openid-connect' ),
		);
		$value           = get_option( 'scouting_oidc_login_redirect' );

		echo '<select id="scouting_oidc_login_redirect" name="scouting_oidc_login_redirect" style="width: 177px;">';
		foreach ( $possible_values as $key => $name ) {
			if ( $value === $key ) {
				echo '<option value="' . esc_attr( $key ) . '" selected>' . esc_html( $name ) . '</option>';
			} else {
				echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $name ) . '</option>';
			}
		}
		echo '</select>';
	}

	/**
	 * Renders custom redirect text field.
	 *
	 * @since 1.2.0
	 */
	public function scouting_oidc_settings_general_custom_redirect_callback(): void {
		$value = get_option( 'scouting_oidc_custom_redirect' );

		// Define your fixed base domain.
		$base_domain = home_url( '/' );

		// Remove base domain from stored value (so only the slug is stored/displayed).
		$slug = '';
		if ( ! empty( $value ) && strpos( $value, $base_domain ) === 0 ) {
			$slug = substr( $value, strlen( $base_domain ) );
		}
		echo '<span style="padding: 5.675px 3px 5.675px 0px;">' . esc_html( $base_domain ) . '</span>';
		echo '<input type="text" id="scouting_oidc_custom_redirect" name="scouting_oidc_custom_redirect" size="50" value="' . esc_attr( $slug ) . '" placeholder="' . esc_attr__( 'custom-page', 'scouting-openid-connect' ) . '"/>';
		echo '<p class="description">' . esc_html__( 'Enter the slug to append to the base URL where users should be redirected after login.', 'scouting-openid-connect' ) . '</p>';
	}

	/**
	 * Renders log retention days number field.
	 *
	 * @since 2.4.0
	 */
	public function scouting_oidc_settings_general_log_retention_days_callback(): void {
		$value = (int) get_option( 'scouting_oidc_log_retention_days', 30 );
		if ( $value < 1 ) {
			$value = 1;
		}

		echo '<input type="number" id="scouting_oidc_log_retention_days" name="scouting_oidc_log_retention_days" min="1" max="3650" step="1" value="' . esc_attr( (string) $value ) . '" style="width: 95px;"/>';
		echo '<p class="description">' . esc_html__( 'Logs may contain personal data such as user IDs and SOL IDs. Set how many days logs are retained before daily cleanup removes older entries.', 'scouting-openid-connect' ) . '</p>';
	}

	/**
	 * Renders debug logging checkbox field.
	 *
	 * @since 2.4.0
	 */
	public function scouting_oidc_settings_general_debug_logging_callback(): void {
		if ( get_option( 'scouting_oidc_debug_logging_enabled' ) ) {
			echo '<input type="checkbox" id="scouting_oidc_debug_logging_enabled" name="scouting_oidc_debug_logging_enabled" checked/>';
		} else {
			echo '<input type="checkbox" id="scouting_oidc_debug_logging_enabled" name="scouting_oidc_debug_logging_enabled"/>';
		}

		echo '<p class="description">' . esc_html__( 'Debug logs are stored in the plugin logs table only when enabled. If WP_DEBUG is enabled, plugin logs are also mirrored to the WordPress/PHP error log.', 'scouting-openid-connect' ) . '</p>';
	}
}
