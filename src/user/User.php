<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . '../../src/utilities/ErrorHandler.php';
require_once plugin_dir_path(__FILE__) . '../../src/utilities/Logger.php';
require_once plugin_dir_path(__FILE__) . '../../src/utilities/Mail.php';

use ScoutingOIDC\ErrorHandler;
use ScoutingOIDC\Logger;
use ScoutingOIDC\Mail;

class User {

    /**
     * @var string SOL Member ID
     */
    private $sol_id;

    /**
     * @var string Email address
     */
    private $email;

    /**
     * @var bool Email address verified
     */
    private $emailVerified;

    /**
     * @var string Language preference
     */
    private $language;

    /**
     * @var string Full name
     */
    private $fullName;

    /**
     * @var string First name
     */
    private $firstName;

    /**
     * @var string Infix
     */
    private $infix;

    /**
     * @var string Family name
     */
    private $familyName;

    /**
     * @var string Gender
     */
    private $gender;

    /**
     * @var string Birthdate
     */
    private $birthdate;

    /**
     * @var string Phone number
     */
    private $phoneNumber;

    /**
     * @var bool Phone number verified
     */
    private $phoneNumberVerified;

    /**
     * @var string Street name
     */
    private $street;

    /**
     * @var string House number
     */
    private $houseNumber;

    /**
     * @var string Postal code
     */
    private $postalCode;

    /**
     * @var string City/Locality
     */
    private $locality;

    /**
     * @var string Country code
     */
    private $countryCode;

    /**
     * @param array $user_json_decoded User information from the OpenID Connect server
     */
    public function __construct(array $user_json_decoded) {
        // Required scopes data
        // Membership scope data
        $this->sol_id = sanitize_user($user_json_decoded['member_id'] ?? null);

        // Email scope data
        $this->email = sanitize_email($user_json_decoded['email'] ?? null);
        $this->emailVerified = rest_sanitize_boolean($user_json_decoded['email_verified'] ?? false);

        // Profile scope data
        $this->fullName = $user_json_decoded['name'] ?? "";
        $this->firstName = $user_json_decoded['given_name'] ?? "";
        $this->infix = $user_json_decoded['infix'] ?? "";
        $this->familyName = $user_json_decoded['family_name'] ?? "";
        $this->gender = $user_json_decoded['gender'] ?? "unknown";
        $this->birthdate = $user_json_decoded['birthdate'] ?? "";

        // Profile scope - Language preference
        $locale = $user_json_decoded['locale'] ?? '';
        $normalized_locale = strtolower(str_replace('-', '_', $locale));
        if ($normalized_locale === 'nl' || strpos($normalized_locale, 'nl_') === 0) {
            $this->language = 'nl_NL';
        } else if ($normalized_locale === 'en' || strpos($normalized_locale, 'en_') === 0) {
            $this->language = 'en_US';
        } else {
            $this->language = ''; // Use default WordPress language
        }

        // Optional scopes data
        // Phone scope data
        $this->phoneNumber = $user_json_decoded['phone_number'] ?? "";
        $this->phoneNumberVerified = rest_sanitize_boolean($user_json_decoded['phone_number_verified'] ?? false);

        // Address scope data
        $address = is_array($user_json_decoded['address'] ?? null) ? $user_json_decoded['address'] : [];
        $this->street = $address['street'] ?? "";
        $this->houseNumber = $address['house_number'] ?? "";
        $this->postalCode = $address['postal_code'] ?? "";
        $this->locality = $address['locality'] ?? "";
        $this->countryCode = $address['country_code'] ?? "";

        // Validate SOL ID is present
        if ($this->sol_id == null) {
            Logger::error(LogType::USER, "Construction of User object failed: SOL ID is missing in the user data received from the OpenID Connect server. User data: " . json_encode($user_json_decoded));
            ErrorHandler::redirect_to_login_error('error', __('SOL ID is missing, make sure the "membership" scope is enabled.', 'scouting-openid-connect'), 'sol_id_is_missing');
        }

        // Validate email is present
        if ($this->email == null) {
            Logger::error(LogType::USER, "Construction of User object failed: Email is missing in the user data received from the OpenID Connect server. User data: " . json_encode($user_json_decoded), null, $this->sol_id);
            ErrorHandler::redirect_to_login_error('error', __('Email is missing, make sure the "email" scope is enabled.', 'scouting-openid-connect'), 'email_is_missing');
        }
    }

    /**
     * Check if user already exists based on SOL ID
     * 
     * @return bool True if user exists, false otherwise
     */
    public function scouting_oidc_user_check_if_exist(): bool {
        return username_exists($this->sol_id) !== false;
    }

    /**
     * Get the username to be used for the WordPress user, which is the SOL ID
     * 
     * @return string Username
     */
    public function getUsername(): string {
        return $this->sol_id;
    }

    /**
     * Get the display name to be used for logging and error messages, which is the full name
     * 
     * @return string Display name
     */
    public function getDisplayName(): string {
        return $this->fullName;
    }

    /**
     * Create a new user
     * 
     * @return void
     */
    public function scouting_oidc_user_create(): void {
        Logger::info(LogType::USER, "Creating a account for user '{$this->fullName}'", null, $this->sol_id);
        $user_id = wp_create_user($this->sol_id, wp_generate_password(18, true, true), $this->email);

        // If user creation failed because the email address is already in use, append the SOL ID to the email (email+sol_id@example.com)
        if (is_wp_error($user_id) && $user_id->get_error_code() === 'existing_user_email') {
            Logger::warning(LogType::USER, "Creating user '{$this->fullName}' failed due to email conflict for '{$this->email}'", null, $this->sol_id);
            if (get_option('scouting_oidc_user_duplicate_email', 'plus_addressing') === 'plus_addressing') {
                 Logger::info(LogType::USER, "Creating user '{$this->fullName}' with plus-addressing strategy to resolve email conflict", null, $this->sol_id);

                // Generate a plus-addressed email using the SOL ID
                $plusAddressEmail = Mail::scouting_oidc_mail_create_plus_address($this->email, $this->sol_id);

                // Check if the plus-addressed email is already in use by another account to avoid conflicts
                $user_id_by_email = email_exists($plusAddressEmail);

                // If the plus-addressed email is already in use by another account that is not the current user, redirect with an error message
                if ($user_id_by_email && $user_id_by_email !== username_exists($this->sol_id)) {
                    Logger::error(LogType::USER, "Creating user '{$this->fullName}' failed: plus-addressed email '{$plusAddressEmail}' is already linked to another account", null, $this->sol_id);
                    ErrorHandler::redirect_to_login_error('error', __('Email address is already in use by another account', 'scouting-openid-connect'), 'login_email_mismatch');
                }

                // Try creating the user again with the plus-addressed email
                $user_id_by_plus_address_email = wp_create_user($this->sol_id, wp_generate_password(18, true, true), $plusAddressEmail);
                if (is_wp_error($user_id_by_plus_address_email)) {
                    Logger::log_wp_error(LogType::USER, LogLevel::ERROR, $user_id_by_plus_address_email, null, $this->sol_id);
                    ErrorHandler::redirect_to_login_error('error', $user_id_by_plus_address_email->get_error_message(), 'login_email_mismatch');
                }
            } else {
                Logger::error(LogType::USER, "Creating user '{$this->fullName}' failed: Email conflict for '{$this->email}' and duplicate-email strategy is not plus-addressing", null, $this->sol_id);
                ErrorHandler::redirect_to_login_error('error', __('Email address is already in use by another account', 'scouting-openid-connect'), 'login_email_mismatch');
            }
        }

        // If user creation failed because of some other reason than email address is already in use then redirect with error message
        if (is_wp_error($user_id)) {
            Logger::log_wp_error(LogType::USER, LogLevel::ERROR, $user_id, null, $this->sol_id);
            ErrorHandler::redirect_to_login_error('error', $user_id->get_error_message(), 'user_creation_failed');
        }

        Logger::info(LogType::USER, "User '{$this->fullName}' created successfully", $user_id, $this->sol_id);

        // Trigger hook after user creation so other plugins can hook into it
        do_action('scouting_oidc_user_register', $user_id, $this->sol_id, $this->email);

        $this->scouting_oidc_user_update_meta($user_id);
    }

    /**
     * Update user data if user already exists
     * 
     * @return void
     */
    public function scouting_oidc_user_update(): void {
        $user_id_by_sol_id = username_exists($this->sol_id);
        $user_id_by_email = email_exists($this->email);

        // User exists by SOL ID and email, and both point to the same account
        if ($user_id_by_sol_id && $user_id_by_email && $user_id_by_sol_id === $user_id_by_email) {
            Logger::info(LogType::USER, "Updating user '{$this->fullName}' where SOL ID and email both match the same existing account", $user_id_by_sol_id, $this->sol_id);
            // Update meta data
            $this->scouting_oidc_user_update_meta($user_id_by_sol_id);
        }
        // User exists by SOL ID and email, but the email belongs to another account
        else if ($user_id_by_sol_id && $user_id_by_email && $user_id_by_sol_id !== $user_id_by_email) {
            Logger::warning(LogType::USER, "Updating user '{$this->fullName}' where SOL ID matches an existing account but email '{$this->email}' is associated with a different account", $user_id_by_sol_id, $this->sol_id);
            /// Handle email conflict based on the setting
            if (get_option('scouting_oidc_user_duplicate_email') === 'plus_addressing') {
                Logger::info(LogType::USER, "Updating user '{$this->fullName}' email address using plus-addressing strategy to resolve conflict", $user_id_by_sol_id, $this->sol_id);

                // Generate a plus-addressed email using the SOL ID
                $plusAddressEmail = Mail::scouting_oidc_mail_create_plus_address($this->email, $this->sol_id);

                // Check if the plus-addressed email is already in use by another account to avoid conflicts
                $user_id_by_plus_address_email = email_exists($plusAddressEmail);

                // If the plus-addressed email is already in use by another account that is not the current user, redirect with an error message
                if ($user_id_by_plus_address_email && $user_id_by_plus_address_email !== $user_id_by_sol_id) {
                    Logger::error(LogType::USER, "Updating user '{$this->fullName}' failed: plus-addressed email '{$plusAddressEmail}' is already linked to another account", $user_id_by_sol_id, $this->sol_id);
                    ErrorHandler::redirect_to_login_error('error', __('Email address is already in use by another account', 'scouting-openid-connect'), 'login_email_mismatch');
                }

                // Plus-addressed email is not in use by another account, safe to update the email to the plus-addressed version
                $result = wp_update_user(array('ID' => $user_id_by_sol_id, 'user_email' => $plusAddressEmail));
                if (is_wp_error($result)) {
                    Logger::log_wp_error(LogType::USER, LogLevel::ERROR, $result, $user_id_by_sol_id, $this->sol_id);
                }
                else {
                    Logger::info(LogType::USER, "Updating user '{$this->fullName}' email address to plus-addressed version '{$plusAddressEmail}' succeeded", $user_id_by_sol_id, $this->sol_id);
                }
            }
            else {
                Logger::error(LogType::USER, "Updating user '{$this->fullName}' failed: Email conflict for '{$this->email}' and duplicate-email strategy is not plus-addressing", $user_id_by_sol_id, $this->sol_id);
                ErrorHandler::redirect_to_login_error('error', __('Email address is already in use by another account', 'scouting-openid-connect'), 'login_email_mismatch');
            }

            // Update meta data
            $this->scouting_oidc_user_update_meta($user_id_by_sol_id);
        }
        // User exists by SOL ID but email is not associated with any account, update email and meta data
        else if ($user_id_by_sol_id && !$user_id_by_email) {
            $user = get_userdata($user_id_by_sol_id); 
            $old_email = $user ? $user->user_email : null;
            Logger::info(LogType::USER, "Updating user '{$this->fullName}' their email address from '{$old_email}' to '{$this->email}'", $user_id_by_sol_id, $this->sol_id);
            // Update email
            $result = wp_update_user(array('ID' => $user_id_by_sol_id, 'user_email' => $this->email));
            if (is_wp_error($result)) {
                Logger::log_wp_error(LogType::USER, LogLevel::ERROR, $result, $user_id_by_sol_id, $this->sol_id);
            }
            else {
                Logger::info(LogType::USER, "Updating user '{$this->fullName}' email address succeeded", $user_id_by_sol_id, $this->sol_id);
            }

            // Update meta data
            $this->scouting_oidc_user_update_meta($user_id_by_sol_id);
        }
        // User not found by either SOL ID or email
        else {
            Logger::error(LogType::USER, "Updating user '{$this->fullName}' failed: no user found for SOL ID '{$this->sol_id}' or email '{$this->email}'", null, $this->sol_id);
            ErrorHandler::redirect_to_login_error('error', __('User not found for update', 'scouting-openid-connect'), 'user_not_found_for_update');
        }

        Logger::info(LogType::USER, "Updating user '{$this->fullName}' finished", $user_id_by_sol_id, $this->sol_id);
    }

    /**
     * Login user
     * 
     * @return void
     */
    public function scouting_oidc_user_login(): void {
        $user = get_user_by('login', $this->sol_id);

        if (!$user) {
            Logger::error(LogType::USER, "User '{$this->fullName}' failed to log in: no user found for SOL ID '{$this->sol_id}'", null, $this->sol_id);
            ErrorHandler::redirect_to_login_error('error', __('Something went wrong while trying to log in', 'scouting-openid-connect'), 'login_email_mismatch');
        }

        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true);

        Logger::info(LogType::USER, "User '{$this->fullName}' logged in successfully", $user->ID, $this->sol_id);

        // Intentionally trigger the core WordPress 'wp_login' action so other plugins
        // that rely on the core login hook are notified when we programmatically log in.
        //
        // PHPCS: The WordPress.NamingConventions.PrefixAllGlobals sniff expects custom
        // hook names to be prefixed. In this case 'wp_login' is a core hook and
        // triggering it intentionally is required for compatibility, so we suppress
        // the sniff for this line.
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action('wp_login', $user->user_login, $user);

        // Also fire a plugin-prefixed action so consumers can hook into this plugin
        // specifically without relying on the generic core hook.
        do_action('scouting_oidc_wp_login', $user->user_login, $user);
    }

    /**
     * Update user meta data
     * 
     * @param int $user_id User ID
     * @return void
     */
    private function scouting_oidc_user_update_meta(int $user_id): void {
        Logger::info(LogType::USER, "Updating user '{$this->fullName}' meta data", $user_id, $this->sol_id);
        update_user_meta($user_id, 'first_name', $this->firstName);
        update_user_meta($user_id, 'last_name', $this->infix . ' ' . $this->familyName);
        update_user_meta($user_id, 'locale', $this->language);
        update_user_meta($user_id, 'show_admin_bar_front', 'false');
        update_user_meta($user_id, 'scouting_oidc_user', 'true');

        if (get_option('scouting_oidc_user_display_name')) {
            switch (get_option('scouting_oidc_user_display_name')) {
                case 'firstname':
                    $display_name = $this->firstName;
                    break;
                case 'lastname':
                    $display_name = $this->infix . ' ' . $this->familyName;
                    break;
                case 'fullname':
                default:
                    $display_name = $this->fullName;
                    break;
            }

            update_user_meta($user_id, 'nickname', $display_name);
            wp_update_user(array('ID' => $user_id, 'display_name' => $display_name));
        }

        if (get_option('scouting_oidc_user_gender')) {
            update_user_meta($user_id, 'scouting_oidc_gender', $this->gender);
        }

        if (get_option('scouting_oidc_user_birthdate')) {
            update_user_meta($user_id, 'scouting_oidc_birthdate', $this->birthdate);
        }

        // Store phone number if available and setting is enabled
        if (get_option('scouting_oidc_user_phone')) {
            update_user_meta($user_id, 'scouting_oidc_phone_number', $this->phoneNumber);
            update_user_meta($user_id, 'scouting_oidc_phone_number_verified', $this->phoneNumberVerified ? 'true' : 'false');
        }

        // Store address data if available and setting is enabled
        if (get_option('scouting_oidc_user_address')) {
            update_user_meta($user_id, 'scouting_oidc_street', $this->street);
            update_user_meta($user_id, 'scouting_oidc_house_number', $this->houseNumber);
            update_user_meta($user_id, 'scouting_oidc_postal_code', $this->postalCode);
            update_user_meta($user_id, 'scouting_oidc_locality', $this->locality);
            update_user_meta($user_id, 'scouting_oidc_country_code', $this->countryCode);
        }

        // Sync the user data to the fields used by WooCommerce if enabled
        if (get_option('scouting_oidc_user_woocommerce_sync')) {
            $this->scouting_oidc_user_sync_to_woocommerce($user_id);
        }

        Logger::info(LogType::USER, "Updating user '{$this->fullName}' meta data finished", $user_id, $this->sol_id);
    }

    /**
     * Sync Scouting OIDC user data to WooCommerce customer data
     * 
     * @param int $user_id User ID
     * @return void
     */
    private function scouting_oidc_user_sync_to_woocommerce(int $user_id): void {
        Logger::info(LogType::USER, "Syncing user '{$this->fullName}' data to WooCommerce", $user_id, $this->sol_id);

        // Map First and Last name
        $first_name = get_user_meta($user_id, 'first_name', true);
        $last_name = get_user_meta($user_id, 'last_name', true);
        if (!empty($first_name)) {
            update_user_meta($user_id, 'billing_first_name', $first_name);
            update_user_meta($user_id, 'shipping_first_name', $first_name);
        }
        if (!empty($last_name)) {
            update_user_meta($user_id, 'billing_last_name', $last_name);
            update_user_meta($user_id, 'shipping_last_name', $last_name);
        }

        // Map phone number
        $phone = get_user_meta($user_id, 'scouting_oidc_phone_number', true);
        if (!empty($phone)) {
            update_user_meta($user_id, 'billing_phone', $phone);
            update_user_meta($user_id, 'shipping_phone', $phone);
        }

        // Map address components
        $street       = get_user_meta($user_id, 'scouting_oidc_street', true);
        $house_number = get_user_meta($user_id, 'scouting_oidc_house_number', true);
        $postal_code  = get_user_meta($user_id, 'scouting_oidc_postal_code', true);
        $city         = get_user_meta($user_id, 'scouting_oidc_locality', true);
        $country      = get_user_meta($user_id, 'scouting_oidc_country_code', true);

        // Combine street and house number
        $address_line1 = trim($street . ' ' . $house_number);

        // Update billing fields when any address data is present
        if ($address_line1 || $postal_code || $city || $country) {
            update_user_meta($user_id, 'billing_address_1', $address_line1);
            update_user_meta($user_id, 'billing_postcode', $postal_code);
            update_user_meta($user_id, 'billing_city', $city);
            update_user_meta($user_id, 'billing_country', $country);
        }

        // Mirror to shipping fields so checkout auto-fills
        if ($address_line1 || $postal_code || $city || $country) {
            update_user_meta($user_id, 'shipping_address_1', $address_line1);
            update_user_meta($user_id, 'shipping_postcode', $postal_code);
            update_user_meta($user_id, 'shipping_city', $city);
            update_user_meta($user_id, 'shipping_country', $country);
        }
        
        Logger::info(LogType::USER, "Syncing user '{$this->fullName}' data to WooCommerce finished", $user_id, $this->sol_id);
    }
}
?>