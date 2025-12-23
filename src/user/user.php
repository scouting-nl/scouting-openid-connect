<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

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
     * @var string Phone number verified
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
            $hint = rawurlencode(__('SOL ID is missing, make sure the "membership" scope is enabled.', 'scouting-openid-connect'));
            $redirect_url = esc_url_raw(wp_login_url() . "?login=failed&error_description=error&hint={$hint}&message=sol_id_is_missing");
            wp_safe_redirect($redirect_url);
            exit;
        }

        // Validate email is present
        if ($this->email == null) {
            $hint = rawurlencode(__('Email is missing, make sure the "email" scope is enabled.', 'scouting-openid-connect'));
            $redirect_url = esc_url_raw(wp_login_url() . "?login=failed&error_description=error&hint={$hint}&message=email_is_missing");
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Check if user already exists based on SOL ID
     * 
     * @return bool True if user exists, false otherwise
     */
    public function scouting_oidc_user_check_if_exist() {
        $user_id = username_exists($this->sol_id);

        if (!$user_id) {
            $email_user_id = email_exists($this->email);
            if ($email_user_id) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                // Intentionally using direct DB update to set user_login in legacy flows. Suppress PHPCS DB and caching warnings.
                $result = $wpdb->update(
                    $wpdb->users,
                    ['user_login' => $this->sol_id],
                    ['ID' => $email_user_id]
                );

                if ($result === false) {
                    $hint = rawurlencode(__('Something went wrong while trying to update the username.', 'scouting-openid-connect'));
                    $redirect_url = esc_url_raw(wp_login_url() . "?login=failed&error_description=error&hint={$hint}&message=username_update_failed");
                    wp_safe_redirect($redirect_url);
                    exit;
                }

                // Clear any cached user data
                if (function_exists('clean_user_cache')) {
                    clean_user_cache($email_user_id);
                }

                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * Create a new user
     */
    public function scouting_oidc_user_create() {
        $user_id = wp_create_user($this->sol_id, wp_generate_password(18, true, true), $this->email);

        if (is_wp_error($user_id)) {
            $hint = rawurlencode($user_id->get_error_message());
            $redirect_url = esc_url_raw(wp_login_url() . "?login=failed&error_description=error&hint={$hint}&message=user_creation_failed");
            wp_safe_redirect($redirect_url);
            exit;
        }

        $this->scouting_oidc_user_update_meta($user_id);
    }

    /**
     * Update user data if user already exists
     */
    public function scouting_oidc_user_update() {
        $user_id_by_sol_id = username_exists($this->sol_id);
        $user_id_by_email = email_exists($this->email);

        // Check if both user IDs exist
        if ($user_id_by_sol_id && $user_id_by_email)
        {
            $user_by_id = get_user_by('login', $this->sol_id);
            $user_by_email = get_user_by('email', $this->email);

            if ($user_by_id->ID == $user_by_email->ID) {
                $user = $user_by_id;
				$this->scouting_oidc_user_update_meta($user->ID);
            }
            else {
                $hint = rawurlencode(__('SOL ID and Email have different user IDs', 'scouting-openid-connect'));
                $redirect_url = esc_url_raw(wp_login_url() . "?login=failed&error_description=error&hint={$hint}&message=login_email_mismatch");
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
        else if ($user_id_by_sol_id) {
            // User exists, but email doesn't match, update email
            $user_by_id = get_user_by('login', $this->sol_id);

            // Update email
            wp_update_user(array('ID' => $user_by_id->ID, 'user_email' => $this->email));

            // Update other meta
			$this->scouting_oidc_user_update_meta($user_by_id->ID);
        }
        else if ($user_id_by_email) {
        	$hint = rawurlencode(__('Email address is already in use by another account', 'scouting-openid-connect'));
            $redirect_url = esc_url_raw(wp_login_url() . "?login=failed&error_description=error&hint={$hint}&message=login_email_mismatch");
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Login user
     */	
    public function scouting_oidc_user_login() {
        $user = get_user_by('login', $this->sol_id);

        if (!$user) {
            $hint = rawurlencode(__('Something went wrong while trying to log in', 'scouting-openid-connect'));
            $redirect_url = esc_url_raw(wp_login_url() . "?login=failed&error_description=error&hint={$hint}&message=login_email_mismatch");
            wp_safe_redirect($redirect_url);
            exit;
        }

        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID, true);

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
     */
    private function scouting_oidc_user_update_meta(int $user_id) {
        update_user_meta($user_id, 'first_name', $this->firstName);
        update_user_meta($user_id, 'last_name', $this->infix . ' ' . $this->familyName);
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
    }

    /**
     * Sync Scouting OIDC user data to WooCommerce customer data
     * 
     * @param int $user_id User ID
     */
    private function scouting_oidc_user_sync_to_woocommerce(int $user_id) {
        // Only run when WooCommerce is active and we have a WP_User instance
        if (!class_exists('WooCommerce') || !($user = get_user_by('ID', $user_id))) {
            return;
        }

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
    }
}
?>