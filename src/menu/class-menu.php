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

/**
 * This class manages the main menu page for the Scouting OIDC plugin, including registering the menu and its submenus.
 *
 * @since 1.0.0
 */
class Menu {

	/**
	 * Registers the main menu page for the plugin.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_menu(): void {
		add_menu_page(
			'Scouting OIDC',
			'Scouting OIDC',
			'manage_options',
			'scouting-oidc-settings',
			'',
			'dashicons-admin-network'
		);
	}
}
