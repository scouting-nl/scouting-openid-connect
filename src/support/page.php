<?php

function scouting_oidc_support_submenu_page() {
    add_submenu_page(
        'scouting-oidc-settings',              // Parent slug (matches the main menu slug)
        'Support',                             // Page title
        'Support',                             // Menu title
        'manage_options',                      // Capability
        'scouting-oidc-support',               // Submenu slug
        'scouting_oidc_support_page_callback', // Callback function
        2                                      // Menu position
    );
}


// Callback to render support page content
function scouting_oidc_support_page_callback() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Support', 'scouting-openid-connect'); ?></h1>
        <p><?php esc_html_e('Before you start make sure you have the role "webmaster" in', 'scouting-openid-connect'); ?> 
            <a href="https://sol.scouting.nl" target="_blank">sol.scouting.nl</a>.
        </p>

        <h2><?php esc_html_e('Setting up OpenID Connect', 'scouting-openid-connect'); ?></h2>
        <ol>
            <li><?php esc_html_e('Go to', 'scouting-openid-connect'); ?> <a href="https://login.scouting.nl" target="_blank">https://login.scouting.nl</a>, <?php esc_html_e('click on "Managed websites" and click on "Add OpenID Connect connection".', 'scouting-openid-connect'); ?></li>
            <li><?php esc_html_e('Add the name of your group/website.', 'scouting-openid-connect'); ?></li>
            <li><?php esc_html_e('Add the Redirect URI:', 'scouting-openid-connect'); ?> <code><?php echo esc_url(home_url('/')); ?></code></li>
            <li><?php esc_html_e('Add the Post Logout Redirect URI:', 'scouting-openid-connect'); ?> <code><?php echo esc_url(home_url('/')); ?></code></li>
            <li><?php esc_html_e('Select the scopes you want to use. The "email" scope is required; the "profile" and "membership" scopes are optional.', 'scouting-openid-connect'); ?></li>
            <li><?php esc_html_e('Select the organizations that can log in.', 'scouting-openid-connect'); ?>
                <br><?php esc_html_e('If your organization has sub-organizations, you can also select "Allow suborganizations."', 'scouting-openid-connect'); ?></li>
            <li><?php esc_html_e('Press "Add Website."', 'scouting-openid-connect'); ?></li>
            <li><?php esc_html_e('Find the website you just created and click on ⓘ.', 'scouting-openid-connect'); ?></li>
            <li><?php esc_html_e('Copy the "Client ID", "Client Secret", and the "Scopes" to the', 'scouting-openid-connect'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=scouting-oidc-settings')); ?>">settings page</a>.
            </li>
            <li><?php esc_html_e('Fill in the OpenID Connect Settings with the copied data. Make sure the required scopes, "openid" and "email", are present.', 'scouting-openid-connect'); ?></li>
            <li><?php esc_html_e('Fill in the General Settings. If you want to store the name, birthdate, or gender, use the scope "profile". If you also want the SOL ID, use the scope "membership".', 'scouting-openid-connect'); ?></li>
            <li><?php esc_html_e('Press "Save Settings"', 'scouting-openid-connect'); ?>.</li>
            <li><?php esc_html_e('Log out and try to log in with the Scouts Login button.', 'scouting-openid-connect'); ?></li>
        </ol>

        <h2><?php esc_html_e('Support', 'scouting-openid-connect'); ?></h2>
        <p><?php esc_html_e('If you need help, please contact', 'scouting-openid-connect'); ?> 
            <a href="mailto:cms@support.scouting.nl?cc=job.van.koeveringe@scouting.nl&subject=WordPress%20Scouting%20OIDC%20Support" target="_blank">cms@support.scouting.nl and job.van.koeveringe@scouting.nl</a>
            <?php esc_html_e('(developer of the plugin)', 'scouting-openid-connect'); ?>.
        </p>
    </div>
    <?php
}
?>