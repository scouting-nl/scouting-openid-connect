<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Fields
{
    /**
     * Show the user profile fields in the user profile and edit user profile pages
     * 
     * @param WP_User $user The user object
     */
    public function scouting_oidc_fields_user_profile($user) {
        ?>
        <h2><?php esc_html_e('Scouts Online (SOL) Profile Information', 'scouting-openid-connect'); ?></h2>

        <table class="form-table" role="presentation">
            <?php
            if (get_option('scouting_oidc_user_birthdate')) {
                $this->scouting_oidc_fields_birthdate($user);
            }
            if (get_option('scouting_oidc_user_gender')) {
                $this->scouting_oidc_fields_gender($user);
            }
            if (get_option('scouting_oidc_user_phone')) {
                $this->scouting_oidc_fields_phone($user);
            }
            if (get_option('scouting_oidc_user_address')) {
                $this->scouting_oidc_fields_address($user);
            }
            ?>
        </table>
        <?php
    }

    /**
     * Display the Birthdate field
     * 
     * @param WP_User $user The user object
     */
    public function scouting_oidc_fields_birthdate($user) {
        ?>
        <tr>
            <th><label for="birthdate"><?php esc_html_e('Birthdate', 'scouting-openid-connect'); ?></label></th>
            <td>
                <input type="date" name="birthdate" id="birthdate" value="<?php echo esc_attr(get_the_author_meta('scouting_oidc_birthdate', $user->ID)); ?>" class="regular-text" readonly disabled/>
            </td>
        </tr>
        <?php
    }

    /**
     * Display the Gender field
     * 
     * @param WP_User $user The user object
     */
    public function scouting_oidc_fields_gender($user) {
        if (get_the_author_meta('scouting_oidc_gender', $user->ID) == '') {
            update_user_meta($user->ID, 'scouting_oidc_gender', 'unknown');
        }
        ?>
        <tr>
            <th><label for="gender"><?php esc_html_e("Gender", "scouting-openid-connect"); ?></label></th>
            <td>
                <select name="gender" id="gender" style="width: 15em;" disabled>
                    <option value="male" <?php selected(get_the_author_meta('scouting_oidc_gender', $user->ID), 'male'); ?>><?php esc_html_e('Male', 'scouting-openid-connect'); ?></option>
                    <option value="female" <?php selected(get_the_author_meta('scouting_oidc_gender', $user->ID), 'female'); ?>><?php esc_html_e('Female', 'scouting-openid-connect'); ?></option>
                    <option value="other" <?php selected(get_the_author_meta('scouting_oidc_gender', $user->ID), 'other'); ?>><?php esc_html_e('Other', 'scouting-openid-connect'); ?></option>
                    <option value="unknown" <?php selected(get_the_author_meta('scouting_oidc_gender', $user->ID), 'unknown'); ?>><?php esc_html_e('Unknown', 'scouting-openid-connect'); ?></option>
                </select>
            </td>
        </tr>
        <?php
    }

    /**
     * Display the Phone field
     * 
     * @param WP_User $user The user object
     */
    public function scouting_oidc_fields_phone($user) {
        $phone_number = get_the_author_meta('scouting_oidc_phone_number', $user->ID);
        $phone_verified = get_the_author_meta('scouting_oidc_phone_number_verified', $user->ID);
        ?>
        <tr>
            <th><label for="phone_number"><?php esc_html_e('Phone Number', 'scouting-openid-connect'); ?></label></th>
            <td>
                <input type="tel" name="phone_number" id="phone_number" value="<?php echo esc_attr($phone_number); ?>" class="regular-text" readonly disabled/>
                <?php if ($phone_verified === 'true'): ?>
                    <span style="color: green;">✓ <?php esc_html_e('Verified', 'scouting-openid-connect'); ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Display the Address fields
     * 
     * @param WP_User $user The user object
     */
    public function scouting_oidc_fields_address($user) {
        $street = get_the_author_meta('scouting_oidc_street', $user->ID);
        $house_number = get_the_author_meta('scouting_oidc_house_number', $user->ID);
        $postal_code = get_the_author_meta('scouting_oidc_postal_code', $user->ID);
        $locality = get_the_author_meta('scouting_oidc_locality', $user->ID);
        $country_code = get_the_author_meta('scouting_oidc_country_code', $user->ID);
        ?>
        <tr>
            <th><label for="address"><?php esc_html_e('Address', 'scouting-openid-connect'); ?></label></th>
            <td>
                <fieldset>
                    <legend class="screen-reader-text"><span><?php esc_html_e('Address', 'scouting-openid-connect'); ?></span></legend>
                    <p>
                        <label for="street"><?php esc_html_e('Street', 'scouting-openid-connect'); ?></label><br>
                        <input type="text" name="street" id="street" value="<?php echo esc_attr($street); ?>" class="regular-text" readonly disabled/>
                    </p>
                    <p>
                        <label for="house_number"><?php esc_html_e('House Number', 'scouting-openid-connect'); ?></label><br>
                        <input type="text" name="house_number" id="house_number" value="<?php echo esc_attr($house_number); ?>" class="regular-text" readonly disabled/>
                    </p>
                    <p>
                        <label for="postal_code"><?php esc_html_e('Postal Code', 'scouting-openid-connect'); ?></label><br>
                        <input type="text" name="postal_code" id="postal_code" value="<?php echo esc_attr($postal_code); ?>" class="regular-text" readonly disabled/>
                    </p>
                    <p>
                        <label for="locality"><?php esc_html_e('City', 'scouting-openid-connect'); ?></label><br>
                        <input type="text" name="locality" id="locality" value="<?php echo esc_attr($locality); ?>" class="regular-text" readonly disabled/>
                    </p>
                    <p>
                        <label for="country_code"><?php esc_html_e('Country Code', 'scouting-openid-connect'); ?></label><br>
                        <input type="text" name="country_code" id="country_code" value="<?php echo esc_attr($country_code); ?>" class="regular-text" readonly disabled/>
                    </p>
                </fieldset>
            </td>
        </tr>
        <?php
    }
}
?>