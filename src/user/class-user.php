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

require_once plugin_dir_path( __FILE__ ) . '../../src/utilities/class-errorhandler.php';
require_once plugin_dir_path( __FILE__ ) . '../../src/utilities/class-logger.php';
require_once plugin_dir_path( __FILE__ ) . '../../src/utilities/class-mail.php';

use ScoutingOIDC\ErrorHandler;
use ScoutingOIDC\Logger;
use ScoutingOIDC\Mail;

/**
 * Maps OpenID Connect claims to WordPress users.
 *
 * @since 1.0.0
 * @since 2.0.0 Changed WordPress user identification to use the SOL member ID.
 * @since 2.5.0 Required the OpenID Connect subject claim.
 */
class User {

	/**
	 * The SOL ID.
	 *
	 * @since 1.0.0
	 * @var string SOL member ID.
	 *
	 * The SOL `member_id` is immutable and unique for each user. It is the
	 * primary account identifier in Scouts Online and is used as the
	 * WordPress username to ensure consistent account mapping across logins.
	 */
	private $sol_id;

	/**
	 * The SOL URL.
	 *
	 * @since 2.5.0
	 * @var string SOL profile URL.
	 */
	private $sol_url;

	/**
	 * The subject.
	 *
	 * @since 2.5.0
	 * @var string OpenID Connect subject.
	 */
	private $subject;

	/**
	 * The email.
	 *
	 * @since 1.0.0
	 * @var string Email address.
	 */
	private $email;

	/**
	 * The email verified.
	 *
	 * @since 1.0.0
	 * @var bool Email address verified.
	 *
	 * SOL3 currently returns `false` for `email_verified` for all users.
	 * Preserve the claim for future compatibility, but do not reject login
	 * based on this value.
	 */
	private $email_verified;

	/**
	 * The language.
	 *
	 * @since 2.2.0
	 * @var string Language preference.
	 */
	private $language;

	/**
	 * The full name.
	 *
	 * @since 1.0.0
	 * @var string Full name.
	 */
	private $full_name;

	/**
	 * The first name.
	 *
	 * @since 1.0.0
	 * @var string First name.
	 */
	private $first_name;

	/**
	 * The infix.
	 *
	 * @since 1.0.0
	 * @var string Infix.
	 */
	private $infix;

	/**
	 * The family name.
	 *
	 * @since 1.0.0
	 * @var string Family name.
	 */
	private $family_name;

	/**
	 * The gender.
	 *
	 * @since 1.0.0
	 * @var string Gender.
	 */
	private $gender;

	/**
	 * The birthdate.
	 *
	 * @since 1.0.0
	 * @var string Birthdate.
	 */
	private $birthdate;

	/**
	 * The phone number.
	 *
	 * @since 2.2.0
	 * @var string Phone number.
	 */
	private $phone_number;

	/**
	 * The phone number verified.
	 *
	 * @since 2.2.0
	 * @var bool Phone number verified.
	 *
	 * SOL3 currently returns `false` for `phone_number_verified` for all users.
	 * Preserve the claim for future compatibility, but do not reject login
	 * based on this value.
	 */
	private $phone_number_verified;

	/**
	 * The street.
	 *
	 * @since 2.2.0
	 * @var string Street name.
	 */
	private $street;

	/**
	 * The house number.
	 *
	 * @since 2.2.0
	 * @var string House number.
	 */
	private $house_number;

	/**
	 * The postal code.
	 *
	 * @since 2.2.0
	 * @var string Postal code.
	 */
	private $postal_code;

	/**
	 * The locality.
	 *
	 * @since 2.2.0
	 * @var string City/Locality.
	 */
	private $locality;

	/**
	 * The country code.
	 *
	 * @since 2.2.0
	 * @var string Country code.
	 */
	private $country_code;

	/**
	 * Initializes the OIDC user model.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Changed the account identity source to the SOL member ID.
	 * @since 2.0.1 Restored compatibility with version 1.2.0 user data.
	 * @since 2.5.0 Required the subject claim and UserInfo profile data.
	 *
	 * @param array $user_json_decoded User information from the OpenID Connect server.
	 */
	public function __construct( array $user_json_decoded ) {
		// Required scopes data
		// Membership scope data.
		$this->sol_id  = sanitize_user( $user_json_decoded['member_id'] ?? null );
		$subject       = $user_json_decoded['sub'] ?? '';
		$this->subject = is_string( $subject ) ? $subject : '';

		// Email scope data.
		$this->email          = sanitize_email( $user_json_decoded['email'] ?? null );
		$this->email_verified = rest_sanitize_boolean( $user_json_decoded['email_verified'] ?? false );

		// Profile scope data.
		$this->full_name   = sanitize_text_field( $user_json_decoded['name'] ?? '' );
		$this->first_name  = sanitize_text_field( $user_json_decoded['given_name'] ?? '' );
		$this->infix       = sanitize_text_field( $user_json_decoded['infix'] ?? '' );
		$this->family_name = sanitize_text_field( $user_json_decoded['family_name'] ?? '' );
		$this->gender      = sanitize_text_field( $user_json_decoded['gender'] ?? 'unknown' );
		$this->birthdate   = sanitize_text_field( $user_json_decoded['birthdate'] ?? '' );
		$this->sol_url     = sanitize_url( $user_json_decoded['profile'] ?? '', array( 'http', 'https' ) );

		// Profile scope - Language preference.
		$locale            = sanitize_text_field( $user_json_decoded['locale'] ?? '' );
		$normalized_locale = strtolower( str_replace( '-', '_', $locale ) );
		if ( 'nl' === $normalized_locale || strpos( $normalized_locale, 'nl_' ) === 0 ) {
			$this->language = 'nl_NL';
		} elseif ( 'en' === $normalized_locale || strpos( $normalized_locale, 'en_' ) === 0 ) {
			$this->language = 'en_US';
		} else {
			$this->language = '';
		}

		// Optional scopes data
		// Phone scope data.
		$this->phone_number          = sanitize_text_field( $user_json_decoded['phone_number'] ?? '' );
		$this->phone_number_verified = rest_sanitize_boolean( $user_json_decoded['phone_number_verified'] ?? false );

		// Address scope data.
		$address            = is_array( $user_json_decoded['address'] ?? null ) ? $user_json_decoded['address'] : array();
		$this->street       = sanitize_text_field( $address['street'] ?? '' );
		$this->house_number = sanitize_text_field( $address['house_number'] ?? '' );
		$this->postal_code  = sanitize_text_field( $address['postal_code'] ?? '' );
		$this->locality     = sanitize_text_field( $address['locality'] ?? '' );
		$this->country_code = sanitize_text_field( $address['country_code'] ?? '' );

		// Validate SOL ID is present.
		if ( empty( $this->sol_id ) ) {
			// Log only which claims are present/absent — never dump personal claim values.
			$present_claims = implode( ', ', array_keys( $user_json_decoded ) );
			Logger::error( LogComponent::USER, 'Construction of User object failed: SOL ID is missing in the user data received from the OpenID Connect server. Present claims: ' . $present_claims );
			ErrorHandler::redirect_to_login_error( 'error', __( 'SOL ID is missing, make sure the "membership" scope is enabled.', 'scouting-openid-connect' ), 'sol_id_is_missing' );
		}

		if ( '' === $this->subject ) {
			Logger::error( LogComponent::USER, 'Construction of User object failed: OIDC subject is missing' );
			ErrorHandler::redirect_to_login_error( 'error', __( 'OpenID Connect subject is missing.', 'scouting-openid-connect' ), 'subject_is_missing' );
		}

		// Validate email is present.
		if ( empty( $this->email ) ) {
			// Log only which claims are present/absent — never dump personal claim values.
			$present_claims = implode( ', ', array_keys( $user_json_decoded ) );
			Logger::error( LogComponent::USER, 'Construction of User object failed: Email is missing in the user data received from the OpenID Connect server. Present claims: ' . $present_claims, null, $this->sol_id );
			ErrorHandler::redirect_to_login_error( 'error', __( 'Email is missing, make sure the "email" scope is enabled.', 'scouting-openid-connect' ), 'email_is_missing' );
		}
	}

	/**
	 * Checks if user already exists based on SOL ID.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Changed account lookup to use the SOL member ID.
	 * @since 2.0.1 Restored compatibility with version 1.2.0 accounts.
	 *
	 * @return bool True if user exists, false otherwise.
	 */
	public function scouting_oidc_user_check_if_exist(): bool {
		$user_id = username_exists( $this->sol_id );
		if ( false === $user_id ) {
			return false;
		}

		$stored_subject  = get_user_meta( $user_id, 'scouting_oidc_subject', true );
		$is_oidc_user    = get_user_meta( $user_id, 'scouting_oidc_user', true ) === 'true';
		$subject_matches = is_string( $stored_subject ) && '' !== $stored_subject && hash_equals( $stored_subject, $this->subject );

		// Existing plugin users without a subject are bound during this login.
		if ( $is_oidc_user && ( '' === $stored_subject || $subject_matches ) ) {
			return true;
		}

		Logger::error( LogComponent::USER, 'Login rejected: existing username is not bound to the OIDC subject', $user_id, $this->sol_id );
		ErrorHandler::redirect_to_login_error( 'error', __( 'This SOL ID is already linked to another account.', 'scouting-openid-connect' ), 'account_binding_mismatch' );
		return false;
	}

	/**
	 * Gets the username to be used for the WordPress user, which is the SOL ID.
	 *
	 * @since 2.4.0
	 *
	 * @return string Username.
	 */
	public function get_username(): string {
		return $this->sol_id;
	}

	/**
	 * Gets the display name to be used for logging and error messages, which is the full name.
	 *
	 * @since 2.4.0
	 *
	 * @return string Display name.
	 */
	public function get_display_name(): string {
		return $this->full_name;
	}

	/**
	 * Creates a new user.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Changed account creation to use the SOL member ID.
	 */
	public function scouting_oidc_user_create(): void {
		Logger::info( LogComponent::USER, "Creating an account for user '{$this->full_name}'", null, $this->sol_id );
		$user_id = wp_create_user( $this->sol_id, wp_generate_password( 18, true, true ), $this->email );

		// If user creation failed because the email address is already in use, append the SOL ID to the email (local-part+sol_id@example.com).
		if ( is_wp_error( $user_id ) && $user_id->get_error_code() === 'existing_user_email' ) {
			Logger::warning( LogComponent::USER, "Creating user '{$this->full_name}' failed due to email conflict for '{$this->email}'", null, $this->sol_id );
			if ( get_option( 'scouting_oidc_user_duplicate_email', 'plus_addressing' ) === 'plus_addressing' ) {
				Logger::info( LogComponent::USER, "Creating user '{$this->full_name}' with plus-addressing strategy to resolve email conflict", null, $this->sol_id );

				// Generate a plus-addressed email using the SOL ID.
				$plus_address_email = Mail::scouting_oidc_mail_create_plus_address( $this->email, $this->sol_id );

				// Check if the plus-addressed email is already in use by another account to avoid conflicts.
				$user_id_by_email = email_exists( $plus_address_email );

				// If the plus-addressed email is already in use by another account that is not the current user, redirect with an error message.
				if ( $user_id_by_email && username_exists( $this->sol_id ) !== $user_id_by_email ) {
					Logger::error( LogComponent::USER, "Creating user '{$this->full_name}' failed: plus-addressed email '{$plus_address_email}' is already linked to another account", null, $this->sol_id );
					ErrorHandler::redirect_to_login_error( 'error', __( 'Email address is already in use by another account', 'scouting-openid-connect' ), 'login_email_mismatch' );
				}

				// Try creating the user again with the plus-addressed email.
				$user_id = wp_create_user( $this->sol_id, wp_generate_password( 18, true, true ), $plus_address_email );
				if ( is_wp_error( $user_id ) ) {
					Logger::log_wp_error( LogComponent::USER, LogLevel::ERROR, $user_id, null, $this->sol_id );
					ErrorHandler::redirect_to_login_error( 'error', $user_id->get_error_message(), 'login_email_mismatch' );
				}
			} else {
				Logger::error( LogComponent::USER, "Creating user '{$this->full_name}' failed: Email conflict for '{$this->email}' and duplicate-email strategy is not plus-addressing", null, $this->sol_id );
				ErrorHandler::redirect_to_login_error( 'error', __( 'Email address is already in use by another account', 'scouting-openid-connect' ), 'login_email_mismatch' );
			}
		}

		// If user creation failed because of some other reason than email address is already in use then redirect with error message.
		if ( is_wp_error( $user_id ) ) {
			Logger::log_wp_error( LogComponent::USER, LogLevel::ERROR, $user_id, null, $this->sol_id );
			ErrorHandler::redirect_to_login_error( 'error', $user_id->get_error_message(), 'user_creation_failed' );
		}

		Logger::info( LogComponent::USER, "User '{$this->full_name}' created successfully", $user_id, $this->sol_id );

		/**
		 * Fires after a user is created through Scouting OpenID Connect.
		 *
		 * @since 2.3.0
		 *
		 * @param int    $user_id WordPress user ID.
		 * @param string $sol_id  Scouts Online member ID.
		 * @param string $email   User email address.
		 */
		do_action( 'scouting_oidc_user_register', $user_id, $this->sol_id, $this->email );

		$this->scouting_oidc_user_update_meta( $user_id );
	}

	/**
	 * Updates user data if user already exists.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Changed account updates to use the SOL member ID.
	 * @since 2.0.1 Restored compatibility with version 1.2.0 accounts.
	 */
	public function scouting_oidc_user_update(): void {
		$user_id_by_sol_id = username_exists( $this->sol_id );
		$user_id_by_email  = email_exists( $this->email );

		// User exists by SOL ID and email, and both point to the same account.
		if ( $user_id_by_sol_id && $user_id_by_email && $user_id_by_sol_id === $user_id_by_email ) {
			Logger::info( LogComponent::USER, "Updating user '{$this->full_name}' where SOL ID and email both match the same existing account", $user_id_by_sol_id, $this->sol_id );
			// Update meta data.
			$this->scouting_oidc_user_update_meta( $user_id_by_sol_id );
		} elseif ( $user_id_by_sol_id && $user_id_by_email && $user_id_by_sol_id !== $user_id_by_email ) {
			// User exists by SOL ID and email, but the email belongs to another account.
			Logger::warning( LogComponent::USER, "Updating user '{$this->full_name}' where SOL ID matches an existing account but email '{$this->email}' is associated with a different account", $user_id_by_sol_id, $this->sol_id );
			// Handle email conflict based on the setting.
			if ( get_option( 'scouting_oidc_user_duplicate_email' ) === 'plus_addressing' ) {
				Logger::info( LogComponent::USER, "Updating user '{$this->full_name}' email address using plus-addressing strategy to resolve conflict", $user_id_by_sol_id, $this->sol_id );

				// Generate a plus-addressed email using the SOL ID.
				$plus_address_email = Mail::scouting_oidc_mail_create_plus_address( $this->email, $this->sol_id );

				// Check if the plus-addressed email is already in use by another account to avoid conflicts.
				$user_id_by_plus_address_email = email_exists( $plus_address_email );

				// If the plus-addressed email is already in use by another account that is not the current user, redirect with an error message.
				if ( $user_id_by_plus_address_email && $user_id_by_plus_address_email !== $user_id_by_sol_id ) {
					Logger::error( LogComponent::USER, "Updating user '{$this->full_name}' failed: plus-addressed email '{$plus_address_email}' is already linked to another account", $user_id_by_sol_id, $this->sol_id );
					ErrorHandler::redirect_to_login_error( 'error', __( 'Email address is already in use by another account', 'scouting-openid-connect' ), 'login_email_mismatch' );
				}

				// Plus-addressed email is not in use by another account, safe to update the email to the plus-addressed version.
				$result = wp_update_user(
					array(
						'ID'         => $user_id_by_sol_id,
						'user_email' => $plus_address_email,
					)
				);
				if ( is_wp_error( $result ) ) {
					Logger::log_wp_error( LogComponent::USER, LogLevel::ERROR, $result, $user_id_by_sol_id, $this->sol_id );
				} else {
					Logger::info( LogComponent::USER, "Updating user '{$this->full_name}' email address to plus-addressed version '{$plus_address_email}' succeeded", $user_id_by_sol_id, $this->sol_id );
				}
			} else {
				Logger::error( LogComponent::USER, "Updating user '{$this->full_name}' failed: Email conflict for '{$this->email}' and duplicate-email strategy is not plus-addressing", $user_id_by_sol_id, $this->sol_id );
				ErrorHandler::redirect_to_login_error( 'error', __( 'Email address is already in use by another account', 'scouting-openid-connect' ), 'login_email_mismatch' );
			}

			// Update meta data.
			$this->scouting_oidc_user_update_meta( $user_id_by_sol_id );
		} elseif ( $user_id_by_sol_id && ! $user_id_by_email ) {
			// User exists by SOL ID but email is not associated with any account, update email and meta data.
			$user      = get_userdata( $user_id_by_sol_id );
			$old_email = $user ? $user->user_email : null;
			Logger::info( LogComponent::USER, "Updating user '{$this->full_name}' their email address from '{$old_email}' to '{$this->email}'", $user_id_by_sol_id, $this->sol_id );
			// Update email.
			$result = wp_update_user(
				array(
					'ID'         => $user_id_by_sol_id,
					'user_email' => $this->email,
				)
			);
			if ( is_wp_error( $result ) ) {
				Logger::log_wp_error( LogComponent::USER, LogLevel::ERROR, $result, $user_id_by_sol_id, $this->sol_id );
			} else {
				Logger::info( LogComponent::USER, "Updating user '{$this->full_name}' email address succeeded", $user_id_by_sol_id, $this->sol_id );
			}

			// Update meta data.
			$this->scouting_oidc_user_update_meta( $user_id_by_sol_id );
		} else {
			// User not found by either SOL ID or email.
			Logger::error( LogComponent::USER, "Updating user '{$this->full_name}' failed: no user found for SOL ID '{$this->sol_id}' or email '{$this->email}'", null, $this->sol_id );
			ErrorHandler::redirect_to_login_error( 'error', __( 'User not found for update', 'scouting-openid-connect' ), 'user_not_found_for_update' );
		}

		Logger::info( LogComponent::USER, "Updating user '{$this->full_name}' finished", $user_id_by_sol_id, $this->sol_id );
	}

		/**
		 * Logs in the user.
		 *
		 * @since 1.0.0
		 * @since 1.0.1 Passed the user object to the core wp_login action.
		 * @since 2.1.0 Fired the plugin-specific scouting_oidc_wp_login action.
		 */
	public function scouting_oidc_user_login(): void {
		$user = get_user_by( 'login', $this->sol_id );

		if ( ! $user ) {
			Logger::error( LogComponent::USER, "User '{$this->full_name}' failed to log in: no user found for SOL ID '{$this->sol_id}'", null, $this->sol_id );
			ErrorHandler::redirect_to_login_error( 'error', __( 'Something went wrong while trying to log in', 'scouting-openid-connect' ), 'login_email_mismatch' );
		}

		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID, true );

		Logger::info( LogComponent::USER, "User '{$this->full_name}' logged in successfully", $user->ID, $this->sol_id );

		/**
		 * Fires after a user is logged in through Scouting OpenID Connect.
		 *
		 * @since 1.0.0
		 *
		 * @param string   $user_login Username.
		 * @param \WP_User $user       Logged-in user object.
		 */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		do_action( 'wp_login', $user->user_login, $user );

		/**
		 * Fires after a Scouting OpenID Connect user is logged in.
		 *
		 * @since 2.1.0
		 *
		 * @param string   $user_login Username.
		 * @param \WP_User $user       Logged-in user object.
		 */
		do_action( 'scouting_oidc_wp_login', $user->user_login, $user );
	}

		/**
		 * Updates user meta data.
		 *
		 * @since 1.0.0
		 * @since 2.0.0 Updated metadata mapping for SOL member ID accounts.
		 * @since 2.1.0 Cleared the WordPress user cache after username updates.
		 *
		 * @param int $user_id User ID.
		 */
	private function scouting_oidc_user_update_meta( int $user_id ): void {
		Logger::info( LogComponent::USER, "Updating user '{$this->full_name}' meta data", $user_id, $this->sol_id );
		update_user_meta( $user_id, 'first_name', $this->first_name );
		update_user_meta( $user_id, 'last_name', $this->infix . ' ' . $this->family_name );
		update_user_meta( $user_id, 'locale', $this->language );
		update_user_meta( $user_id, 'show_admin_bar_front', 'false' );
		update_user_meta( $user_id, 'scouting_oidc_user', 'true' );
		update_user_meta( $user_id, 'scouting_oidc_subject', $this->subject );
		update_user_meta( $user_id, 'scouting_oidc_sol_url', $this->sol_url );

		if ( get_option( 'scouting_oidc_user_display_name' ) ) {
			switch ( get_option( 'scouting_oidc_user_display_name' ) ) {
				case 'firstname':
					$display_name = $this->first_name;
					break;
				case 'lastname':
					$display_name = $this->infix . ' ' . $this->family_name;
					break;
				case 'fullname':
				default:
					$display_name = $this->full_name;
					break;
			}

			update_user_meta( $user_id, 'nickname', $display_name );
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $display_name,
				)
			);
		}

		if ( get_option( 'scouting_oidc_user_gender' ) ) {
			update_user_meta( $user_id, 'scouting_oidc_gender', $this->gender );
		}

		if ( get_option( 'scouting_oidc_user_birthdate' ) ) {
			update_user_meta( $user_id, 'scouting_oidc_birthdate', $this->birthdate );
		}

		// Store phone number if available and setting is enabled.
		if ( get_option( 'scouting_oidc_user_phone' ) ) {
			update_user_meta( $user_id, 'scouting_oidc_phone_number', $this->phone_number );
			update_user_meta( $user_id, 'scouting_oidc_phone_number_verified', $this->phone_number_verified ? 'true' : 'false' );
		}

		// Store address data if available and setting is enabled.
		if ( get_option( 'scouting_oidc_user_address' ) ) {
			update_user_meta( $user_id, 'scouting_oidc_street', $this->street );
			update_user_meta( $user_id, 'scouting_oidc_house_number', $this->house_number );
			update_user_meta( $user_id, 'scouting_oidc_postal_code', $this->postal_code );
			update_user_meta( $user_id, 'scouting_oidc_locality', $this->locality );
			update_user_meta( $user_id, 'scouting_oidc_country_code', $this->country_code );
		}

		// Sync the user data to the fields used by WooCommerce if enabled.
		if ( get_option( 'scouting_oidc_user_woocommerce_sync' ) ) {
			$this->scouting_oidc_user_sync_to_woocommerce( $user_id );
		}

		Logger::info( LogComponent::USER, "Updating user '{$this->full_name}' meta data finished", $user_id, $this->sol_id );
	}

		/**
		 * Synchronizes Scouting OIDC user data to WooCommerce customer data.
		 *
		 * @since 2.2.0
		 *
		 * @param int $user_id User ID.
		 */
	private function scouting_oidc_user_sync_to_woocommerce( int $user_id ): void {
		Logger::info( LogComponent::USER, "Syncing user '{$this->full_name}' data to WooCommerce", $user_id, $this->sol_id );

		// Map First and Last name.
		$first_name = get_user_meta( $user_id, 'first_name', true );
		$last_name  = get_user_meta( $user_id, 'last_name', true );
		if ( ! empty( $first_name ) ) {
			update_user_meta( $user_id, 'billing_first_name', $first_name );
			update_user_meta( $user_id, 'shipping_first_name', $first_name );
		}
		if ( ! empty( $last_name ) ) {
			update_user_meta( $user_id, 'billing_last_name', $last_name );
			update_user_meta( $user_id, 'shipping_last_name', $last_name );
		}

		// Map phone number.
		$phone = get_user_meta( $user_id, 'scouting_oidc_phone_number', true );
		if ( ! empty( $phone ) ) {
			update_user_meta( $user_id, 'billing_phone', $phone );
			update_user_meta( $user_id, 'shipping_phone', $phone );
		}

		// Map address components.
		$street       = get_user_meta( $user_id, 'scouting_oidc_street', true );
		$house_number = get_user_meta( $user_id, 'scouting_oidc_house_number', true );
		$postal_code  = get_user_meta( $user_id, 'scouting_oidc_postal_code', true );
		$city         = get_user_meta( $user_id, 'scouting_oidc_locality', true );
		$country      = get_user_meta( $user_id, 'scouting_oidc_country_code', true );

		// Combine street and house number.
		$address_line1 = trim( $street . ' ' . $house_number );

		// Update billing fields when any address data is present.
		if ( $address_line1 || $postal_code || $city || $country ) {
			update_user_meta( $user_id, 'billing_address_1', $address_line1 );
			update_user_meta( $user_id, 'billing_postcode', $postal_code );
			update_user_meta( $user_id, 'billing_city', $city );
			update_user_meta( $user_id, 'billing_country', $country );
		}

		// Mirror to shipping fields so checkout auto-fills.
		if ( $address_line1 || $postal_code || $city || $country ) {
			update_user_meta( $user_id, 'shipping_address_1', $address_line1 );
			update_user_meta( $user_id, 'shipping_postcode', $postal_code );
			update_user_meta( $user_id, 'shipping_city', $city );
			update_user_meta( $user_id, 'shipping_country', $country );
		}

		Logger::info( LogComponent::USER, "Syncing user '{$this->full_name}' data to WooCommerce finished", $user_id, $this->sol_id );
	}
}
