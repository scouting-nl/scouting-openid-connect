<?php
/**
 * Modify the description of the Scouting OpenID Connect plugin
 * 
 * @param array $all_plugins all plugins with their information
 * @return array All plugins with their information including the modified description
 */
function scouting_oidc_modify_plugin_description($all_plugins) {
	if (isset($all_plugins['scouting-openid-connect/scouting-openid-connect.php'])) {
		$description = __('WordPress plugin for logging in with Scouting Nederland OpenID Connect Server.', 'scouting-openid-connect');
		$all_plugins['scouting-openid-connect/scouting-openid-connect.php']['Description'] = $description;
	}
	return $all_plugins;
}
?>