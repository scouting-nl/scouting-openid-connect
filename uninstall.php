<?php 
// Exit if uninstall constant is not defined
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

global $wpdb;

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
    'scouting_oidc_user_woocommerce_sync',
    'scouting_oidc_user_auto_create',
    'scouting_oidc_user_redirect',
    'scouting_oidc_login_redirect',
    'scouting_oidc_custom_redirect',
    'scouting_oidc_debug_logging_enabled',
    'scouting_oidc_logs_schema_version',
);

foreach ($scouting_oidc_options as $scouting_oidc_option) {
    delete_option($scouting_oidc_option);
}

// Delete transients
$scouting_oidc_transients = array(
    'scouting_oidc_well_known_data',
    'scouting_oidc_jwks_data',
);

foreach ($scouting_oidc_transients as $scouting_oidc_transient) {
    delete_transient($scouting_oidc_transient);
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

$scouting_oidc_metas_placeholders = implode(', ', array_fill(0, count($scouting_oidc_metas), '%s'));
$scouting_oidc_delete_usermeta_sql = "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ($scouting_oidc_metas_placeholders)";
$scouting_oidc_delete_usermeta_args = array_merge(array($scouting_oidc_delete_usermeta_sql), $scouting_oidc_metas);

// Delete all matching plugin user meta in one query for scalability.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query($wpdb->prepare($scouting_oidc_delete_usermeta_sql, $scouting_oidc_metas));

// Drop the logs table if it exists
$scouting_oidc_logs_table = $wpdb->prefix . 'scouting_oidc_logs';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $scouting_oidc_logs_table));
?>