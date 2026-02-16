<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Actions
{
	/**
	 * Add settings link to scouting-oidc plugin on the plugins page
	 * 
	 * @param array $links all links of the plugin
	 * @return array links with added settings link
	 */
	public function scouting_oidc_actions_plugin_links(array $links): array {
		array_unshift($links, '<a href="'.esc_url(get_admin_url(null, 'admin.php?page=scouting-oidc-settings')).'">' . __("Settings", "scouting-openid-connect") . '</a>');
		return $links;
	}
}
?>