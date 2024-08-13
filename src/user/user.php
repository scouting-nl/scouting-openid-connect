<?php

class User {

    /**
     * @var string Username
     */
    private $userName;

    /**
     * @var string Email address
     */
    private $email;

    /**
     * @var bool Email address verified
     */
    private $emailVerified;

    /**
     * @var int SOL ID
     */
    private $sol_id;

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
     * @param array $user_json_decoded User information from the OpenID Connect server
     */
    public function __construct(array $user_json_decoded) {
        $this->userName = get_option('scouting_oidc_user_name_prefix').$user_json_decoded['sub'];
        $this->email = $user_json_decoded['email'] ?? null;
        $this->emailVerified = $user_json_decoded['email_verified'] ?? false;
        $this->sol_id = $user_json_decoded['member_id'] ?? "";
        $this->fullName = $user_json_decoded['name'] ?? "";
        $this->firstName = $user_json_decoded['given_name'] ?? "";
        $this->infix = $user_json_decoded['infix'] ?? "";
        $this->familyName = $user_json_decoded['family_name'] ?? "";
        $this->gender = $user_json_decoded['gender'] ?? "unknown";
        $this->birthdate = $user_json_decoded['birthdate'] ?? "";

        if ($this->email == null) {
            wp_safe_redirect(wp_login_url() . '?error_description=error&hint=' . __("Email scope is missing", "scouting-openid-connect") . '&message=email_is_missing');
            exit;
        }
    }

    /**
     * Check if user already exists
     * 
     * @return bool True if user exists, false otherwise
     */
    public function checkIfUserExist() {
        $user_id = username_exists($this->userName);
        $email_id = email_exists($this->email);

        if (!$user_id && !$email_id) {
            return false;
        }

        return true;
    }

    /**
     * Create a new user
     * 
     * @return int User ID
     */
    public function createUser() {
        $user_id = wp_create_user($this->userName, wp_generate_password(18, true, true), $this->email);

        if (is_wp_error($user_id)) {
            return 0;
        }

        $this->updateUserMeta($user_id);

        return $user_id;
    }

    /**
     * Update user meta data
     * 
     * @param int $user_id User ID
     */
    public function updateUserMeta(int $user_id) {
        update_user_meta($user_id, 'first_name', $this->firstName);
        update_user_meta($user_id, 'scouting_oidc_infix', $this->infix);
        update_user_meta($user_id, 'last_name', $this->familyName);
        update_user_meta($user_id, 'show_admin_bar_front', 'false');

        if (get_option('scouting_oidc_user_display_name')) {
            switch (get_option('scouting_oidc_user_display_name')) {
                case 'firstname':
                    $display_name = $this->firstName;
                    break;
                case 'lastname':
                    $display_name = $this->infix.$this->familyName;
                    break;
                case 'username':
                    $display_name = $this->userName;
                    break;
                case 'fullname':
                default:
                    $display_name = $this->fullName;
                    break;
            }

            update_user_meta($user_id, 'nickname', $display_name);
            wp_update_user(array('ID' => $user_id, 'display_name' => $display_name));
        }

        if (get_option('scouting_oidc_user_scouting_id')) {
            update_user_meta($user_id, 'scouting_oidc_id', $this->sol_id);
        }

        if (get_option('scouting_oidc_user_gender')) {
            update_user_meta($user_id, 'scouting_oidc_gender', $this->gender);
        }

        if (get_option('scouting_oidc_user_birthdate')) {
            update_user_meta($user_id, 'scouting_oidc_birthdate', $this->birthdate);
        }
    }

    /**
     * Update user data if user already exists
     */
    public function updateUser() {
        $user_name = username_exists($this->userName);
        $email = email_exists($this->email);

        if ($user_name && $email)
        {
            $user_username = get_user_by('login', $this->userName);
            $user_email = get_user_by('email', $this->email);

            if ($user_username->ID == $user_email->ID) {
                $user = $user_username;
            }
            else {
                wp_safe_redirect(wp_login_url() . '?error_description=error&hint=' . __("Username and Email have different user ID", "scouting-openid-connect") . '&message=login_email_mismatch');
                exit;
            }
        }
        else if ($user_name) {
            $user = get_user_by('login', $this->userName);

            //update email
            wp_update_user(array('ID' => $user->ID, 'user_email' => $this->email));
        }
        else if ($email) {
            $user = get_user_by('email', $this->email);
        }

        $this->updateUserMeta($user->ID);
    }

    /**
     * Login user
     * 
     * @return bool True if user is logged in, false otherwise
     */	
    public function loginUser() {
        $user = get_user_by('login', $this->userName);

        if (!$user) {
            wp_safe_redirect(wp_login_url() . '?error_description=error&hint=' . __("Something went wrong while trying to log in", "scouting-openid-connect") . '&message=login_email_mismatch');
            exit;
        }

        wp_set_current_user($user->ID, $user->user_login);
        wp_set_auth_cookie($user->ID);
        do_action('wp_login', $user->user_login);
    }
}
?>