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
 * This class modifies the plugin description for the Scouting OIDC plugin on the WordPress plugins page.
 *
 * @since 1.0.0
 */
class Description {

	/**
	 * Modifies the description of the Scouting OpenID Connect plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param array $all_plugins all plugins with their information.
	 * @return array All plugins with their information including the modified description.
	 */
	public function scouting_oidc_description_modify_plugin( array $all_plugins ): array {
		if ( isset( $all_plugins['scouting-openid-connect/scouting-openid-connect.php'] ) ) {
			$description = __( 'WordPress plugin for logging in with Scouting Nederland OpenID Connect Server.', 'scouting-openid-connect' );
			$all_plugins['scouting-openid-connect/scouting-openid-connect.php']['Description'] = $description;
		}
		return $all_plugins;
	}
}
