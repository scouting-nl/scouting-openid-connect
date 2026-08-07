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
    'scouting_oidc_user_duplicate_email',
    'scouting_oidc_user_redirect',
    'scouting_oidc_login_redirect',
    'scouting_oidc_custom_redirect',
    'scouting_oidc_debug_logging_enabled',
    'scouting_oidc_log_retention_days',
    'scouting_oidc_last_log_cleanup',
    'scouting_oidc_logs_schema_version',
);

foreach ($scouting_oidc_options as $scouting_oidc_option) {
    delete_option($scouting_oidc_option);
}

// Delete plugin transient rows.
$scouting_oidc_transient_prefixes = array(
    'scouting_oidc_wk_',
    'scouting_oidc_jwks_',
    'scouting_oidc_session_',
);

// Build LIKE patterns for both transient and transient_timeout rows for each prefix to delete all relevant transients in a single query for scalability.
$scouting_oidc_transient_like_patterns = array();
foreach ($scouting_oidc_transient_prefixes as $scouting_oidc_transient_prefix) {
    $scouting_oidc_escaped_prefix = $wpdb->esc_like($scouting_oidc_transient_prefix);
    $scouting_oidc_transient_like_patterns[] = '_transient_' . $scouting_oidc_escaped_prefix . '%';
    $scouting_oidc_transient_like_patterns[] = '_transient_timeout_' . $scouting_oidc_escaped_prefix . '%';
}

// Prepare the WHERE clause with placeholders for each LIKE pattern based on the number of patterns to delete and construct the full SQL query to delete all matching transients in one go for better performance.
$scouting_oidc_transient_where_clause = implode(' OR ', array_fill(0, count($scouting_oidc_transient_like_patterns), 'option_name LIKE %s'));
$scouting_oidc_delete_transients_sql = "DELETE FROM {$wpdb->options} WHERE $scouting_oidc_transient_where_clause";

// Delete all matching transient and timeout rows in one query.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query($wpdb->prepare($scouting_oidc_delete_transients_sql, $scouting_oidc_transient_like_patterns));

// Delete user meta
$scouting_oidc_metas = array(
    'scouting_oidc_user',
    'scouting_oidc_subject',
    'scouting_oidc_sol_url',
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

// Prepare placeholders for the IN clause based on the number of meta keys to delete
$scouting_oidc_metas_placeholders = implode(', ', array_fill(0, count($scouting_oidc_metas), '%s'));
$scouting_oidc_delete_usermeta_sql = "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ($scouting_oidc_metas_placeholders)";

// Delete all matching plugin user meta in one query for scalability.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query($wpdb->prepare($scouting_oidc_delete_usermeta_sql, $scouting_oidc_metas));

// Drop the logs table if it exists
$scouting_oidc_logs_table = $wpdb->prefix . 'scouting_oidc_logs';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $scouting_oidc_logs_table));
?>