<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 1.0.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-settings-oidc.php';
require_once plugin_dir_path( __FILE__ ) . 'class-settings-general.php';

use ScoutingOIDC\Settings_Oidc;
use ScoutingOIDC\Settings_General;

/**
 * Manages the Scouting OIDC settings page, including rendering the page,
 * initializing settings sections and fields, and handling default options.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Registers the settings submenu page under the main menu.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_submenu_page(): void {
		add_submenu_page(
			'scouting-oidc-settings',
			'Settings',
			'Settings',
			'manage_options',
			'scouting-oidc-settings',
			array( $this, 'scouting_oidc_settings_page_callback' ),
			1
		);
	}

	/**
	 * Renders the settings page content.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_page_callback(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'scouting-openid-connect' ); ?></h1>
			<p>
				<?php esc_html_e( 'Need help with setting up?', 'scouting-openid-connect' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=scouting-oidc-support' ) ); ?>"><?php esc_html_e( 'Go to the support page', 'scouting-openid-connect' ); ?></a>.
			</p>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'scouting_oidc_settings_group' );
				do_settings_sections( 'scouting-openid-connect-settings' );
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Initializes the settings page and register all settings sections and fields.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_page_init(): void {
		$scouting_oidc_settings_oidc = new Settings_Oidc();
		$scouting_oidc_settings_oidc->scouting_oidc_settings_oidc();

		$scouting_oidc_settings_general = new Settings_General();
		$scouting_oidc_settings_general->scouting_oidc_settings_general();
	}

	/**
	 * Enqueues the settings script that shows and hides fields based on other field
	 * values.
	 *
	 * @since 2.4.0
	 */
	public function scouting_oidc_fields_enqueue_settings_script(): void {
		// Enqueue the external JavaScript file.
		wp_enqueue_script(
			'scouting-oidc-settings-script',
			plugins_url( 'settings.js', __FILE__ ),
			array(),
			SCOUTING_OIDC_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
	}

	/**
	 * Sets default options upon plugin activation.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_settings_install(): void {
		// Set default options for OIDC.
		add_option( 'scouting_oidc_client_id', '' );
		add_option( 'scouting_oidc_client_secret', '' );
		add_option( 'scouting_oidc_scopes', 'openid membership profile email address phone' );

		// Set default options for general settings.
		add_option( 'scouting_oidc_user_display_name', 'fullname' );
		add_option( 'scouting_oidc_user_birthdate', false );
		add_option( 'scouting_oidc_user_gender', false );
		add_option( 'scouting_oidc_user_phone', false );
		add_option( 'scouting_oidc_user_address', false );
		add_option( 'scouting_oidc_user_woocommerce_sync', false );
		add_option( 'scouting_oidc_user_auto_create', true );
		add_option( 'scouting_oidc_user_duplicate_email', 'plus_addressing' );
		add_option( 'scouting_oidc_user_redirect', true );
		add_option( 'scouting_oidc_login_redirect', 'frontpage' );
		add_option( 'scouting_oidc_custom_redirect', '' );
		add_option( 'scouting_oidc_debug_logging_enabled', false );
		add_option( 'scouting_oidc_log_retention_days', 30 );
	}
}
