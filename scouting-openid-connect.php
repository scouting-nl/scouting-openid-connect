<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Scouting OpenID Connect
 *
 * @category   Scouting OpenID Connect
 * @package    scouting-openid-connect
 * @author     Job van Koeveringe <job.van.koeveringe@scouting.nl>
 * @copyright  2025 Scouting Nederland
 * @license    GPLv3
 * @version    2.1.0
 * @link       https://github.com/Scouting-nl/scouting-openid-connect
 *
 * @wordpress-plugin
 * Plugin Name:          Scouting OpenID Connect
 * Plugin URI:           https://github.com/Scouting-nl/scouting-openid-connect
 * Description:          WordPress plugin for logging in with Scouting Nederland OpenID Connect Server.
 * Version:              2.1.0
 * Requires at least:    6.6.0
 * Requires PHP:         8.2
 * Author:               Job van Koeveringe
 * Author URI:           https://jobvankoeveringe.com?utm_source=wordpress&utm_medium=plugin&utm_campaign=scouting_oidc
 * License:              GPLv3
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:          scouting-openid-connect
 * Domain Path:          /languages
 **/

define('SCOUTING_OIDC_PATH', plugin_dir_path( __FILE__ ));
require_once SCOUTING_OIDC_PATH . 'src/auth/auth.php';
require_once SCOUTING_OIDC_PATH . 'src/auth/session.php';
require_once SCOUTING_OIDC_PATH . 'src/menu/menu.php';
require_once SCOUTING_OIDC_PATH . 'src/settings/page.php';
require_once SCOUTING_OIDC_PATH . 'src/shortcode/page.php';
require_once SCOUTING_OIDC_PATH . 'src/support/page.php';
require_once SCOUTING_OIDC_PATH . 'src/plugin/actions.php';
require_once SCOUTING_OIDC_PATH . 'src/plugin/description.php';
require_once SCOUTING_OIDC_PATH . 'src/user/fields.php';

use ScoutingOIDC\Auth;
use ScoutingOIDC\Session;
use ScoutingOIDC\Menu;
use ScoutingOIDC\Actions;
use ScoutingOIDC\Description;
use ScoutingOIDC\Settings;
use ScoutingOIDC\Shortcode;
use ScoutingOIDC\Support;
use ScoutingOIDC\Fields;

$scouting_oidc_auth = new Auth();
$scouting_oidc_session = new Session();
$scouting_oidc_menu = new Menu();
$scouting_oidc_actions = new Actions();
$scouting_oidc_description = new Description();
$scouting_oidc_settings = new Settings();
$scouting_oidc_shortcode = new Shortcode();
$scouting_oidc_support = new Support();
$scouting_oidc_fields = new Fields();

// Init plugin
function scouting_oidc_init()
{
    global $scouting_oidc_auth, $scouting_oidc_actions, $scouting_oidc_fields, $scouting_oidc_shortcode, $scouting_oidc_settings; // Declare global variables

    // Add the OpenID Connect button to the login form
    add_action('login_form', array($scouting_oidc_auth, 'scouting_oidc_auth_login_form'));

    // Create shortcodes for OpenID Connect button and link
    add_shortcode('scouting_oidc_button', array($scouting_oidc_auth, 'scouting_oidc_auth_login_button_shortcode'));
    add_shortcode('scouting_oidc_link', array($scouting_oidc_auth, 'scouting_oidc_auth_login_url_shortcode'));

    // Provide additional links in the plugin overview page
	add_filter('plugin_action_links_'.plugin_basename(__FILE__), [$scouting_oidc_actions, 'scouting_oidc_actions_plugin_links']);

    // Add user profile fields if any option is enabled
	if (get_option('scouting_oidc_user_birthdate') || get_option('scouting_oidc_user_gender') || get_option('scouting_oidc_user_phone') || get_option('scouting_oidc_user_address'))
	{
		add_action('show_user_profile', [$scouting_oidc_fields, 'scouting_oidc_fields_user_profile']);
		add_action('edit_user_profile', [$scouting_oidc_fields, 'scouting_oidc_fields_user_profile']);
	}

    // Enqueue scripts for admin pages
    add_action('admin_enqueue_scripts', [$scouting_oidc_shortcode, 'scouting_oidc_shortcode_enqueue_live_script']);
    add_action('admin_enqueue_scripts', [$scouting_oidc_settings, 'scouting_oidc_fields_enqueue_hide_field_script']);
}
add_action('plugins_loaded', 'scouting_oidc_init');

// Add pages to the admin menu
add_action('admin_menu', [$scouting_oidc_menu, 'scouting_oidc_menu']);
add_action('admin_menu', [$scouting_oidc_settings, 'scouting_oidc_settings_submenu_page']);
add_action('admin_menu', [$scouting_oidc_shortcode, 'scouting_oidc_shortcode_submenu_page']);
add_action('admin_menu', [$scouting_oidc_support, 'scouting_oidc_support_submenu_page']);

// Hook into admin_init to initialize settings
add_action('admin_init', [$scouting_oidc_settings, 'scouting_oidc_settings_page_init']);

// Callback to render settings page content
add_action('template_redirect', [$scouting_oidc_auth, 'scouting_oidc_auth_callback']);

// Add login error message
add_filter('login_message', [$scouting_oidc_auth, 'scouting_oidc_auth_login_failed']);

// Modify plugin description
add_filter('all_plugins', [$scouting_oidc_description, 'scouting_oidc_description_modify_plugin']);

// Add display to safe style css for user profile fields
add_filter('safe_style_css', function( $styles ) {
    $styles[] = 'display';
    return $styles;
});

// Add login redirect
add_action('wp_login', [$scouting_oidc_auth, 'scouting_oidc_auth_login_redirect']);

// Add logout redirect
add_action('wp_logout', [$scouting_oidc_auth, 'scouting_oidc_auth_logout_redirect']);

// Setup defaults during installation
register_activation_hook(__FILE__, [$scouting_oidc_settings, 'scouting_oidc_settings_install']);
?>