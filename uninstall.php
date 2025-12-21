<?php 
// Exit if uninstall constant is not defined
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

// Delete options
$scouting_oidc_options = array(
	'scouting_oidc_client_id',
	'scouting_oidc_client_secret',
	'scouting_oidc_scopes',
	'scouting_oidc_user_display_name',
	'scouting_oidc_user_birthdate',
	'scouting_oidc_user_gender',
	'scouting_oidc_user_phone',
	'scouting_oidc_user_address',
	'scouting_oidc_user_address_sync',
	'scouting_oidc_user_auto_create',
	'scouting_oidc_user_redirect',
	'scouting_oidc_login_redirect',
	'scouting_oidc_custom_redirect',
);

foreach ($scouting_oidc_options as $scouting_oidc_option) {
	if (get_option($scouting_oidc_option)) delete_option($scouting_oidc_option);
}

// Delete transients
$scouting_oidc_transients = array(
	'scouting_oidc_well_known_data',
	'scouting_oidc_jwks_data',
);

foreach ($scouting_oidc_transients as $scouting_oidc_transient) {
	if (get_transient($scouting_oidc_transient)) delete_transient($scouting_oidc_transient);
}

// Delete user meta
$scouting_oidc_metas = array(
	'scouting_oidc_user',
	'scouting_oidc_birthdate',
	'scouting_oidc_gender',
	'scouting_oidc_phone_number',
	'scouting_oidc_phone_number_verified',
	'scouting_oidc_street',
	'scouting_oidc_house_number',
	'scouting_oidc_postal_code',
	'scouting_oidc_locality',
	'scouting_oidc_country_code',
);
$scouting_oidc_users = get_users();

foreach ($scouting_oidc_users as $scouting_oidc_user) {
	foreach ($scouting_oidc_metas as $scouting_oidc_meta) {
		if (get_user_meta($scouting_oidc_user->ID, $scouting_oidc_meta)) delete_user_meta($scouting_oidc_user->ID, $scouting_oidc_meta);
	}
}
?>