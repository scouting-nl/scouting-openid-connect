<?php
/**
 * Scouting OpenID Connect
 *
 * @category   Scouting OpenID Connect
 * @package    scouting-openid-connect
 * @author     Job van Koeveringe <job.van.koeveringe@scouting.nl>
 * @copyright  2026 Scouting Nederland
 * @license    GPLv3
 * @version    2.5.0
 * @since      1.0.0
 * @link       https://github.com/Scouting-nl/scouting-openid-connect
 *
 * @wordpress-plugin
 * Plugin Name:          Scouting OpenID Connect
 * Plugin URI:           https://github.com/Scouting-nl/scouting-openid-connect
 * Description:          WordPress plugin for logging in with Scouting Nederland OpenID Connect Server.
 * Version:              2.5.0
 * Requires at least:    6.9.5
 * Requires PHP:         8.2
 * Author:               Job van Koeveringe
 * Author URI:           https://jobvankoeveringe.com?utm_source=wordpress&utm_medium=plugin&utm_campaign=scouting_oidc
 * License:              GPLv3
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:          scouting-openid-connect
 * Domain Path:          /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


define( 'SCOUTING_OIDC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCOUTING_OIDC_VERSION', '2.5.0' );
/**
 * Loads the class-auth.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/auth/class-auth.php';
/**
 * Loads the class-session.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/auth/class-session.php';
/**
 * Loads the class-menu.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/menu/class-menu.php';
/**
 * Loads the class-settings.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/settings/class-settings.php';
/**
 * Loads the class-shortcode.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/shortcode/class-shortcode.php';
/**
 * Loads the class-support.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/support/class-support.php';
/**
 * Loads the class-sitehealth.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/support/class-sitehealth.php';
/**
 * Loads the class-logging.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/logging/class-logging.php';
/**
 * Loads the class-actions.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/plugin/class-actions.php';
/**
 * Loads the class-description.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/plugin/class-description.php';
/**
 * Loads the class-fields.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/user/class-fields.php';
/**
 * Loads the class-logger.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/utilities/class-logger.php';
/**
 * Loads the class-cronjobs.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/utilities/class-cronjobs.php';
/**
 * Loads the class-mail.php implementation.
 */
require_once SCOUTING_OIDC_PATH . 'src/utilities/class-mail.php';

use ScoutingOIDC\Auth;
use ScoutingOIDC\Session;
use ScoutingOIDC\Menu;
use ScoutingOIDC\Actions;
use ScoutingOIDC\Description;
use ScoutingOIDC\Settings;
use ScoutingOIDC\Shortcode;
use ScoutingOIDC\Support;
use ScoutingOIDC\ProviderHealth;
use ScoutingOIDC\SiteHealth;
use ScoutingOIDC\Logging;
use ScoutingOIDC\Fields;
use ScoutingOIDC\CronJobs;
use ScoutingOIDC\Mail;
use ScoutingOIDC\Logger;

$scouting_oidc_auth            = new Auth();
$scouting_oidc_session         = new Session();
$scouting_oidc_menu            = new Menu();
$scouting_oidc_actions         = new Actions();
$scouting_oidc_description     = new Description();
$scouting_oidc_settings        = new Settings();
$scouting_oidc_shortcode       = new Shortcode();
$scouting_oidc_support         = new Support();
$scouting_oidc_provider_health = new ProviderHealth();
$scouting_oidc_site_health     = new SiteHealth( $scouting_oidc_provider_health );
$scouting_oidc_logging         = new Logging();
$scouting_oidc_fields          = new Fields();
$scouting_oidc_logger          = new Logger();
$scouting_oidc_cron_jobs       = new CronJobs();

/**
 * Registers the plugin's WordPress hooks.
 *
 * @since 1.0.0
 */
function scouting_oidc_init(): void {
	global $scouting_oidc_auth, $scouting_oidc_actions, $scouting_oidc_fields, $scouting_oidc_shortcode, $scouting_oidc_settings;

	// Add the OpenID Connect button to the login form.
	add_action( 'login_form', array( $scouting_oidc_auth, 'scouting_oidc_auth_login_form' ) );

	// Create shortcodes for OpenID Connect button and link.
	add_shortcode( 'scouting_oidc_button', array( $scouting_oidc_auth, 'scouting_oidc_auth_login_button_shortcode' ) );
	add_shortcode( 'scouting_oidc_link', array( $scouting_oidc_auth, 'scouting_oidc_auth_login_url_shortcode' ) );

	// Provide additional links in the plugin overview page.
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $scouting_oidc_actions, 'scouting_oidc_actions_plugin_links' ) );

	// Add a link to Scouts Online after View in each applicable user row.
	add_filter( 'user_row_actions', array( $scouting_oidc_fields, 'scouting_oidc_fields_user_row_actions' ), 10, 2 );

	// Normalize plus-addressed Scouting OIDC recipient aliases in outgoing mail.
	add_filter( 'wp_mail', array( Mail::class, 'scouting_oidc_mail_filter_wp_mail' ), 20 );

	// Add user profile fields if any option is enabled.
	if ( get_option( 'scouting_oidc_user_birthdate' ) || get_option( 'scouting_oidc_user_gender' ) || get_option( 'scouting_oidc_user_phone' ) || get_option( 'scouting_oidc_user_address' ) ) {
		add_action( 'show_user_profile', array( $scouting_oidc_fields, 'scouting_oidc_fields_user_profile' ) );
		add_action( 'edit_user_profile', array( $scouting_oidc_fields, 'scouting_oidc_fields_user_profile' ) );
	}

	// Enqueue scripts for admin pages.
	add_action( 'admin_enqueue_scripts', array( $scouting_oidc_shortcode, 'scouting_oidc_shortcode_enqueue_live_script' ) );
	add_action( 'admin_enqueue_scripts', array( $scouting_oidc_settings, 'scouting_oidc_fields_enqueue_settings_script' ) );
}
add_action( 'plugins_loaded', 'scouting_oidc_init' );
add_action( 'plugins_loaded', array( $scouting_oidc_logger, 'scouting_oidc_logger_maybe_upgrade_database' ) );

// Add pages to the admin menu.
add_action( 'admin_menu', array( $scouting_oidc_menu, 'scouting_oidc_menu' ) );
add_action( 'admin_menu', array( $scouting_oidc_settings, 'scouting_oidc_settings_submenu_page' ) );
add_action( 'admin_menu', array( $scouting_oidc_shortcode, 'scouting_oidc_shortcode_submenu_page' ) );
add_action( 'admin_menu', array( $scouting_oidc_support, 'scouting_oidc_support_submenu_page' ) );
add_action( 'admin_menu', array( $scouting_oidc_logging, 'scouting_oidc_logging_submenu_page' ) );

// Hook into admin_init to initialize settings.
add_action( 'admin_init', array( $scouting_oidc_settings, 'scouting_oidc_settings_page_init' ) );

// Callback to render settings page content.
add_action( 'template_redirect', array( $scouting_oidc_auth, 'scouting_oidc_auth_callback' ) );

// Add login error message.
add_filter( 'login_message', array( $scouting_oidc_auth, 'scouting_oidc_auth_login_failed' ) );

// Modify plugin description.
add_filter( 'all_plugins', array( $scouting_oidc_description, 'scouting_oidc_description_modify_plugin' ) );

// Add plugin checks and redacted diagnostics to WordPress Site Health.
add_filter( 'site_status_tests', array( $scouting_oidc_site_health, 'site_health_tests' ) );
add_filter( 'debug_information', array( $scouting_oidc_site_health, 'debug_information' ) );
add_action( 'rest_api_init', array( $scouting_oidc_provider_health, 'register_route' ) );

// Add display to safe style css for user profile fields.
add_filter(
	'safe_style_css',
	function ( array $styles ): array {
		$styles[] = 'display';
		return $styles;
	}
);

// Add login redirect.
add_action( 'wp_login', array( $scouting_oidc_auth, 'scouting_oidc_auth_login_redirect' ) );

// Add logout redirect.
add_action( 'wp_logout', array( $scouting_oidc_auth, 'scouting_oidc_auth_logout_redirect' ), 10, 1 );

// Daily cleanup for logs older than the configured retention period.
add_action( CronJobs::CLEANUP_CRON_HOOK, array( CronJobs::class, 'scouting_oidc_logger_cleanup_old_logs' ) );

// Ensure log cleanup schedule exists during runtime.
add_action( 'init', array( $scouting_oidc_cron_jobs, 'scouting_oidc_logger_schedule_cleanup' ) );

// Allow administrators to recover an overdue cleanup directly from Site Health.
add_action( 'admin_post_' . CronJobs::RUN_CLEANUP_ACTION, array( $scouting_oidc_cron_jobs, 'scouting_oidc_logger_run_cleanup_now' ) );

// Setup defaults during installation.
register_activation_hook( __FILE__, array( $scouting_oidc_settings, 'scouting_oidc_settings_install' ) );
register_activation_hook( __FILE__, array( $scouting_oidc_logger, 'scouting_oidc_logger_database_create' ) );
register_activation_hook( __FILE__, array( $scouting_oidc_cron_jobs, 'scouting_oidc_cron_activate' ) );
register_deactivation_hook( __FILE__, array( $scouting_oidc_cron_jobs, 'scouting_oidc_cron_deactivate' ) );
