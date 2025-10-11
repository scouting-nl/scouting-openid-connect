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
}
?>