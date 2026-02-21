<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Support
{
    public function scouting_oidc_support_submenu_page(): void {
        add_submenu_page(
            'scouting-oidc-settings',                       // Parent slug (matches the main menu slug)
            'Support',                                      // Page title
            'Support',                                      // Menu title
            'manage_options',                               // Capability
            'scouting-oidc-support',                        // Submenu slug
            [$this, 'scouting_oidc_support_page_callback'], // Callback function
            3                                               // Menu position
        );
    }

    // Callback to render support page content
    public function scouting_oidc_support_page_callback(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Support', 'scouting-openid-connect'); ?></h1>
            <p><?php esc_html_e('Before you start make sure you have the role "webmaster" in', 'scouting-openid-connect'); ?> 
                <a href="https://mijn.scouting.nl" target="_blank">mijn.scouting.nl</a>.
            </p>
    
            <h2><?php esc_html_e('Setting up OpenID Connect', 'scouting-openid-connect'); ?></h2>
            <ol>
                <li><?php esc_html_e('Go to', 'scouting-openid-connect'); ?> <a href="https://login.scouting.nl" target="_blank">login.scouting.nl</a>, <?php esc_html_e('click on "Managed websites" and click on "Add OpenID Connect connection".', 'scouting-openid-connect'); ?></li>
                <li><?php esc_html_e('Add the name of your group/website.', 'scouting-openid-connect'); ?></li>
                <li><?php esc_html_e('Add the Redirect URI:', 'scouting-openid-connect'); ?> <code><?php echo esc_url(home_url('/')); ?></code></li>
                <li><?php esc_html_e('Add the Post Logout Redirect URI:', 'scouting-openid-connect'); ?> <code><?php echo esc_url(home_url('/')); ?></code></li>
                <li><?php esc_html_e('Select the scopes you want to use. The `Email`, `Personal` and `Membership` scopes are required;', 'scouting-openid-connect'); ?>
                    <br><?php esc_html_e('The `Address`, `Phone number` scope is optional.', 'scouting-openid-connect'); ?>
                    <br><?php esc_html_e('Currently the `Parents/guardians` scope is not supported.', 'scouting-openid-connect'); ?></li>
                <li><?php esc_html_e('Select the organizations that can log in.', 'scouting-openid-connect'); ?>
                    <br><?php esc_html_e('If your organization has sub-organizations, you can also select "Allow suborganizations".', 'scouting-openid-connect'); ?></li>
                <li><?php esc_html_e('Select to use the PKCE (code challenge).', 'scouting-openid-connect'); ?></li>
                <li><?php esc_html_e('Press "Add Website".', 'scouting-openid-connect'); ?></li>
                <li><?php esc_html_e('Find the website you just created and click on ⓘ.', 'scouting-openid-connect'); ?></li>
                <li><?php esc_html_e('Copy the "Client ID", "Client Secret", and the "Scopes" to the', 'scouting-openid-connect'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=scouting-oidc-settings')); ?>">settings page</a>.
                </li>
                <li>
                    <?php esc_html_e('Fill in the OpenID Connect Settings with the copied data.', 'scouting-openid-connect'); ?>
                    <br><?php esc_html_e('Required scopes:', 'scouting-openid-connect'); ?>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>openid</li>
                        <li>membership</li>
                        <li>profile</li>
                        <li>email</li>
                    </ul>
                    <?php esc_html_e('Optional scopes:', 'scouting-openid-connect'); ?>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>address</li>
                        <li>phone</li>
                    </ul>
                </li>
                <li><?php esc_html_e('Fill in the General Settings.', 'scouting-openid-connect'); ?></li>
                <li><?php esc_html_e('Press "Save Settings"', 'scouting-openid-connect'); ?>.</li>
                <li><?php esc_html_e('Log out and try to log in with the Scouts Login button.', 'scouting-openid-connect'); ?></li>
            </ol>
    
            <h2><?php esc_html_e('Support', 'scouting-openid-connect'); ?></h2>
            <p><?php esc_html_e('If you need help, please contact', 'scouting-openid-connect'); ?> 
                <a href="mailto:cms@support.scouting.nl?cc=job.van.koeveringe@scouting.nl&subject=WordPress%20Scouting%20OIDC%20Support" target="_blank">cms@support.scouting.nl & job.van.koeveringe@scouting.nl</a>
                <?php esc_html_e('(developer of the plugin)', 'scouting-openid-connect'); ?>.
            </p>
        </div>
        <?php
    }   
}
?>