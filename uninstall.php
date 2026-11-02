<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 1.0.0
 */

// Exit if uninstall constant is not defined.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Revoke only roles positively recorded as added by the plugin, preserving pre-existing roles.
$scouting_oidc_user_offset = 0;
do {
	$scouting_oidc_owned_users = get_users(
		array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only positively owned roles can safely be revoked.
			'meta_key' => 'scouting_oidc_owned_wordpress_role',
			'number'   => 100,
			'offset'   => $scouting_oidc_user_offset,
		)
	);
	if ( '' !== $wpdb->last_error ) {
		wp_die( esc_html__( 'Your access level could not be updated. Please contact the site administrator.', 'scouting-openid-connect' ) );
	}
	foreach ( $scouting_oidc_owned_users as $scouting_oidc_owned_user ) {
		$scouting_oidc_owned_roles = get_user_meta( $scouting_oidc_owned_user->ID, 'scouting_oidc_owned_wordpress_role', true );
		// Earlier development builds stored one positively owned role as a string.
		if ( is_string( $scouting_oidc_owned_roles ) ) {
			$scouting_oidc_owned_roles = '' === $scouting_oidc_owned_roles ? array() : array( $scouting_oidc_owned_roles );
		}
		if ( ! is_array( $scouting_oidc_owned_roles ) ) {
			wp_die( esc_html__( 'Your access level could not be updated. Please contact the site administrator.', 'scouting-openid-connect' ) );
		}
		foreach ( $scouting_oidc_owned_roles as $scouting_oidc_owned_role ) {
			if ( ! is_string( $scouting_oidc_owned_role ) || '' === $scouting_oidc_owned_role ) {
				wp_die( esc_html__( 'Your access level could not be updated. Please contact the site administrator.', 'scouting-openid-connect' ) );
			}
			$scouting_oidc_owned_user->remove_role( $scouting_oidc_owned_role );
			$scouting_oidc_saved_user = get_userdata( $scouting_oidc_owned_user->ID );
			if ( ! $scouting_oidc_saved_user instanceof WP_User || in_array( $scouting_oidc_owned_role, $scouting_oidc_saved_user->roles, true ) ) {
				wp_die( esc_html__( 'Your access level could not be updated. Please contact the site administrator.', 'scouting-openid-connect' ) );
			}
		}
	}
	$scouting_oidc_owned_count  = count( $scouting_oidc_owned_users );
	$scouting_oidc_user_offset += 100;
} while ( 100 === $scouting_oidc_owned_count );

// Delete options.
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
	'scouting_oidc_roles_schema_version',
	'scouting_oidc_sol_role_mapping_rules',
	'scouting_oidc_roles_add_default_role',
	'scouting_oidc_woocommerce_require_sol_login',
);

foreach ( $scouting_oidc_options as $scouting_oidc_option ) {
	delete_option( $scouting_oidc_option );
}

// Delete plugin transient rows.
$scouting_oidc_transient_prefixes = array(
	'scouting_oidc_wk_',
	'scouting_oidc_jwks_',
	'scouting_oidc_session_',
);

// Build LIKE patterns for both transient and transient_timeout rows for each prefix to delete all relevant transients in a single query for scalability.
$scouting_oidc_transient_like_patterns = array();
foreach ( $scouting_oidc_transient_prefixes as $scouting_oidc_transient_prefix ) {
	$scouting_oidc_escaped_prefix            = $wpdb->esc_like( $scouting_oidc_transient_prefix );
	$scouting_oidc_transient_like_patterns[] = '_transient_' . $scouting_oidc_escaped_prefix . '%';
	$scouting_oidc_transient_like_patterns[] = '_transient_timeout_' . $scouting_oidc_escaped_prefix . '%';
}

// Prepare the WHERE clause with placeholders for each LIKE pattern based on the number of patterns to delete and construct the full SQL query to delete all matching transients in one go for better performance.
$scouting_oidc_transient_where_clause = implode( ' OR ', array_fill( 0, count( $scouting_oidc_transient_like_patterns ), 'option_name LIKE %s' ) );
$scouting_oidc_delete_transients_sql  = "DELETE FROM {$wpdb->options} WHERE $scouting_oidc_transient_where_clause";

// Delete all matching transient and timeout rows in one query.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( $wpdb->prepare( $scouting_oidc_delete_transients_sql, $scouting_oidc_transient_like_patterns ) );

// Delete other plugin user meta after positively owned roles have been revoked.
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
	'scouting_oidc_owned_wordpress_role',
	'scouting_oidc_roles_available',
);

// Prepare placeholders for the IN clause based on the number of meta keys to delete.
$scouting_oidc_metas_placeholders  = implode( ', ', array_fill( 0, count( $scouting_oidc_metas ), '%s' ) );
$scouting_oidc_delete_usermeta_sql = "DELETE FROM {$wpdb->usermeta} WHERE meta_key IN ($scouting_oidc_metas_placeholders)";

// Delete all matching plugin user meta in one query for scalability.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( $wpdb->prepare( $scouting_oidc_delete_usermeta_sql, $scouting_oidc_metas ) );

// Delete product restrictions configured by the plugin.
$scouting_oidc_product_metas = array(
	'_scouting_oidc_allowed_organisation_ids',
	'_scouting_oidc_allowed_role_ids',
	'_scouting_oidc_denied_organisation_ids',
	'_scouting_oidc_denied_role_ids',
	'_scouting_oidc_allowed_include_descendants',
	'_scouting_oidc_denied_include_descendants',
	'_scouting_oidc_allowed_require_both',
	'_scouting_oidc_denied_require_both',
	'_scouting_oidc_require_sol_login',
);

foreach ( $scouting_oidc_product_metas as $scouting_oidc_product_meta ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- A fixed plugin meta key is removed once during uninstall.
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => $scouting_oidc_product_meta ), array( '%s' ) );
}

// Drop the logs table if it exists.
$scouting_oidc_logs_table = $wpdb->prefix . 'scouting_oidc_logs';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $scouting_oidc_logs_table ) );

// Drop the role tables in dependency order.
$scouting_oidc_roles_tables = array(
	$wpdb->prefix . 'scouting_oidc_sol_role_assignments',
	$wpdb->prefix . 'scouting_oidc_sol_roles',
	$wpdb->prefix . 'scouting_oidc_sol_organisation_units',
	$wpdb->prefix . 'scouting_oidc_sol_organisation_parents',
	$wpdb->prefix . 'scouting_oidc_sol_organisations',
);

foreach ( $scouting_oidc_roles_tables as $scouting_oidc_roles_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $scouting_oidc_roles_table ) );
}
