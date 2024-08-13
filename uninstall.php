<?php 
// exit if uninstall constant is not defined
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// Delete options
$options = array(
	'scouting_oidc_client_id',
	'scouting_oidc_client_secret',
	'scouting_oidc_scopes',
    'scouting_oidc_user_display_name',
	'scouting_oidc_user_birthdate',
	'scouting_oidc_user_gender',
    'scouting_oidc_user_scouting_id',
	'scouting_oidc_user_name_prefix',
	'scouting_oidc_user_auto_create',
    'scouting_oidc_login_redirect',
);

foreach ($options as $option) {
	if (get_option($option)) delete_option($option);
}

//Delete user meta
$metas = array(
	'scouting_oidc_id',
	'scouting_oidc_birthdate',
	'scouting_oidc_scopes',
	'scouting_oidc_infix',
);
$users = get_users();

foreach ($users as $user) {
	foreach ($metas as $meta) {
		if (get_user_meta($user->ID, $meta)) delete_user_meta($user->ID, $meta);
	}
}
?>