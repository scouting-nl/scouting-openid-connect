<?php
/**
 * Scouting OpenID Connect
 *
 * @category   Scouting OpenID Connect
 * @package    scouting-openid-connect
 * @author     Job van Koeveringe <job.van.koeveringe@scouting.nl>
 * @copyright  2024 Scouting Nederland
 * @license    GPLv3
 * @version    0.0.1
 * @link       https://github.com/Scouting-nl/OpenID-Connect-Wordpress
 *
 * @wordpress-plugin
 * Plugin Name:          Scouting OpenID Connect
 * Plugin URI:           https://github.com/Scouting-nl/OpenID-Connect-Wordpress
 * Description:          WordPress plugin for logging in with Scouting Nederland OpenID Connect Server.
 * Version:              0.0.1
 * Requires at least:    6.4.3
 * Requires PHP:         8.2
 * Author:               Job van Koeveringe
 * Author URI:           https://jobvankoeveringe.com?utm_source=wordpress&utm_medium=plugin&utm_campaign=scouting_oidc
 * License:              GPLv3
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:          scouting-openid-connect
 * Domain Path:          /languages
 */

require_once 'src/auth/auth.php';
require_once 'src/auth/session.php';
require_once 'src/menu/menu.php';
require_once 'src/settings/page.php';
require_once 'src/support/page.php';
include_once 'src/plugin/actions.php';
include_once 'src/plugin/description.php';
include_once 'src/user/fields.php';

$auth = new Auth();

// Init plugin
function scouting_oidc_init()
{
    // Add translations to the plugin
    load_plugin_textdomain('scouting-openid-connect', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Add the OpenID Connect button to the login form
    add_action('login_form', array($GLOBALS['auth'], 'scouting_oidc_login_form'));

    // Create shortcodes for OpenID Connect button and link
    add_shortcode('scouting_oidc_button', array($GLOBALS['auth'], 'scouting_oidc_login_button_shortcode'));
    add_shortcode('scouting_oidc_link', array($GLOBALS['auth'], 'scouting_oidc_login_url_shortcode'));

    // Geef extra links in de plugin-overzichtspagina
	add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'scouting_oidc_plugin_action_links');

    // Add scouting ID, birthday and gender to user profile 
	if (get_option('scouting_oidc_user_scouting_id') || get_option('scouting_oidc_user_birthday') || get_option('scouting_oidc_user_gender'))
	{
		add_action('show_user_profile', 'scouting_oidc_user_profile_fields');
		add_action('edit_user_profile', 'scouting_oidc_user_profile_fields');
	}

    // Add infix field to user profile
    add_action('show_user_profile', 'add_infix_field_html');
    add_action('edit_user_profile', 'add_infix_field_html');
    add_action('admin_enqueue_scripts', 'enqueue_infix_field_script');
}
add_action('plugins_loaded', 'scouting_oidc_init');

// Start session
add_action('init', 'scouting_oidc_start_session');

// Add your settings page in the WordPress admin menu
add_action('admin_menu', 'scouting_oidc_menu');
add_action('admin_menu', 'scouting_oidc_settings_submenu_page');
add_action('admin_menu', 'scouting_oidc_support_submenu_page');

// Hook into admin_init to initialize settings
add_action('admin_init', 'scouting_oidc_settings_page_init');

// Callback to render settings page content
add_action('template_redirect', array($auth, 'scouting_oidc_callback'));

// Add login error message
add_filter('login_message', array($auth, 'scouting_oidc_login_failed'));

// Modify plugin description
add_filter('all_plugins', 'scouting_oidc_modify_plugin_description');

// Add display to safe style css for user profile fields
add_filter( 'safe_style_css', function( $styles ) {
    $styles[] = 'display';
    return $styles;
} );

// add login redirect
add_action('wp_login', array($auth, 'scouting_oidc_login_redirect'));

// add logout redirect
add_action('wp_logout', array($auth, 'scouting_oidc_logout_redirect'));

// Setup defaults during installation
register_activation_hook(__FILE__, 'scouting_oidc_settings_install');
?>