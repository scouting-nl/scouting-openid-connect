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
 * Manages WordPress actions and filters for Scouting OIDC, including settings
 * links on the Plugins page.
 *
 * @since 1.0.0
 */
class Actions {

	/**
	 * Adds settings link to scouting-oidc plugin on the plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param array $links all links of the plugin.
	 * @return array links with added settings link.
	 */
	public function scouting_oidc_actions_plugin_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( get_admin_url( null, 'admin.php?page=scouting-oidc-settings' ) ) . '">' . __( 'Settings', 'scouting-openid-connect' ) . '</a>' );
		return $links;
	}
}
