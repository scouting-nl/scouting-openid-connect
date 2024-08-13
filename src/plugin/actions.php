<?php
/**
 * Add settings link to scouting-oidc plugin on the plugins page
 * 
 * @param array $links all links of the plugin
 * @return array links with added settings link
 */
function scouting_oidc_plugin_action_links($links) {
	array_unshift($links, '<a href="'.esc_url(get_admin_url(null, 'admin.php?page=scouting-oidc-settings')).'">' . __("Settings", "scouting-openid-connect") . '</a>');
	return $links;
}
?>