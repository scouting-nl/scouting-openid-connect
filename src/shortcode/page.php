<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Shortcode
{
    public function scouting_oidc_shortcode_submenu_page() {
        add_submenu_page(
            'scouting-oidc-settings',                         // Parent slug (matches the main menu slug)
            'Shortcode',                                      // Page title
            'Shortcode',                                      // Menu title
            'manage_options',                                 // Capability
            'scouting-oidc-shortcode',                        // Submenu slug
            [$this, 'scouting_oidc_shortcode_page_callback'], // Callback function
            2                                                 // Menu position
        );
    }

    // Callback to render support page content
    public function scouting_oidc_shortcode_page_callback() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Shortcode', 'scouting-openid-connect'); ?></h1>
            <!-- short explanation of what are shortcodes in general -->
            <p>
                <?php esc_html_e('Shortcodes are small pieces of code that allow you to easily add dynamic content to your WordPress site, enabling you to embed files or create objects with just one line of code.', 'scouting-openid-connect'); ?>
                <br>
                <?php esc_html_e('They can be used for various purposes, such as adding galleries, embedding videos, or displaying specific content types.', 'scouting-openid-connect'); ?>
                <br>
                <?php esc_html_e('To use a shortcode, simply insert it into the content area of your post or page, and WordPress will parse it, replacing it with the corresponding content when viewed.', 'scouting-openid-connect'); ?>
                <br>
                <?php esc_html_e('For more information on how to use shortcodes and their benefits, visit the following link: ', 'scouting-openid-connect'); ?>
                <a href="https://wordpress.com/support/wordpress-editor/blocks/shortcode-block/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('WordPress Shortcode Block Support', 'scouting-openid-connect'); ?></a>
            </p>

            <h2 id="shortcodes"><?php esc_html_e('Shortcodes for OpenID Connect', 'scouting-openid-connect'); ?></h2>

            <h3 id="openid-button"><?php esc_html_e('OpenID Connect Button', 'scouting-openid-connect'); ?></h3>
            <div style='content: ""; display: table; clear: both;'>
                <div style="float: left; width: 50%; padding-right: 10px; border-right: 2px solid #8c8f94; box-sizing: border-box;">
                    <h4><?php esc_html_e('Button Example', 'scouting-openid-connect'); ?></h4>
                    <p>
                        <?php esc_html_e('The OpenID Connect button shortcode allows you to add a button to your WordPress site that users can click to log in using their Scouts Online account.', 'scouting-openid-connect'); ?>
                        <br>
                        <?php esc_html_e('To add the OpenID Connect button to your site, use the following shortcode:', 'scouting-openid-connect'); ?>
                    </p>
                    <pre><code>[scouting_oidc_button]</code></pre>
                    <p><?php esc_html_e('You can customize the appearance of the button by adding attributes to the shortcode. The following attributes are available:', 'scouting-openid-connect'); ?></p>
                    <ul>
                        <li><code>width</code>: <?php esc_html_e('The width of the button in pixels.', 'scouting-openid-connect'); ?></li>
                        <li><code>height</code>: <?php esc_html_e('The height of the button in pixels.', 'scouting-openid-connect'); ?></li>
                        <li><code>background_color</code>: <?php esc_html_e('The background color of the button.', 'scouting-openid-connect'); ?></li>
                        <li><code>text_color</code>: <?php esc_html_e('The text color of the button.', 'scouting-openid-connect'); ?></li>
                    </ul>
                </div>
                <div style="float: left; width: 50%; padding-left: 10px; box-sizing: border-box;">
                    <h4><?php esc_html_e('Live Shortcode Editor', 'scouting-openid-connect'); ?></h4>
                    <form>
                        <label for="scoutingOIDCWidthInput">Width</label>
                        <input type="number" id="scoutingOIDCWidthInput" value="250" min="120" style="width: 75px;">
                        <p class="description"><?php esc_html_e('Default is 250px, minimum is 120px. If the width is smaller than 225px, the logo will be removed.', 'scouting-openid-connect'); ?></p>

                        <label for="scoutingOIDCHeightInput">Height</label>
                        <input type="number" id="scoutingOIDCHeightInput" value="40" min="40" style="width: 75px;">
                        <p class="description"><?php esc_html_e('Default is 40px, minimum is 40px.', 'scouting-openid-connect'); ?></p>

                        <label for="scoutingOIDCBackgroundColorInput">Background Color</label>
                        <input type="color" id="scoutingOIDCBackgroundColorInput" value="#4caf50" style="width: 75px;">
                        <p class="description"><?php esc_html_e('The default color is #4caf50.', 'scouting-openid-connect'); ?></p>

                        <label for="scoutingOIDCTextColorInput">Text Color</label>
                        <input type="color" id="scoutingOIDCTextColorInput" value="#ffffff" style="width: 75px;">
                        <p class="description"><?php esc_html_e('The default color is #ffffff.', 'scouting-openid-connect'); ?></p>
                    </form>
                </div>
            </div>

            <div>
                <p><?php esc_html_e('Example of the shortcode with custom attributes:', 'scouting-openid-connect'); ?></p>
                <pre><code id="scoutingOIDCButtonShortCode">[scouting_oidc_button width="250" height="40" background_color="#4caf50" text_color="#ffffff"]</code></pre>
                <p><?php esc_html_e('Example of the shortcode above:', 'scouting-openid-connect'); ?></p>
                <?php echo do_shortcode('[scouting_oidc_button width="250" height="40" background_color="#4caf50" text_color="#ffffff"]'); ?>
                <p><strong><?php esc_html_e('Note: The button is not interactive in this preview.', 'scouting-openid-connect'); ?></strong></p>
            </div>

            <hr id="scouding-oidc-divider" style="border-top: 2px solid #8c8f94; border-radius: 4px; margin: 20px 0px;"/>

            <h3 id="openid-link"><?php esc_html_e('OpenID Connect Link', 'scouting-openid-connect'); ?></h3>
            <p>
                <?php esc_html_e('The OpenID Connect link shortcode allows you to add a text link to your WordPress site that users can click to log in using their Scouts Online account.', 'scouting-openid-connect'); ?>
                <br>
                <?php esc_html_e('To add the OpenID Connect link to your site, use the following shortcode:', 'scouting-openid-connect'); ?>
            </p>
            <pre><code>[scouting_oidc_link]</code></pre>
            <p><?php esc_html_e('You can not customize the appearance of the link.', 'scouting-openid-connect'); ?></p>
            <p><?php esc_html_e('Example of the link shortcode:', 'scouting-openid-connect'); ?></p>
            <p><?php echo do_shortcode('[scouting_oidc_link]'); ?><br><strong><?php esc_html_e('Note: Do not copy this link, it will not work. This is just an example of how the link will look like.', 'scouting-openid-connect'); ?></strong></p>
        </div>
        <?php
    }

    /**
     * This script renders JavaScript to live preview the shortcode with custom attributes
     */
    public function scouting_oidc_shortcode_enqueue_live_script() {
        // Enqueue the external JavaScript file with the defer attribute
        wp_enqueue_script(
            'live-shortcode-script',                    // Handle name
            plugins_url('live-shortcode.js', __FILE__), // Path to the file
            array(),                                    // No dependencies
            "2.2.0",                                    // Version number
            array(
                'strategy' => 'defer',                  // Add the defer attribute
                'in_footer' => true                     // Load the script in the footer
            )
        );
    }
}
?>