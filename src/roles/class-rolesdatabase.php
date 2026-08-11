<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since Unreleased Adds role database support.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and upgrades the tables used to store Scouts Online roles.
 *
 * @since Unreleased Creates and upgrades role assignment tables.
 */
class RolesDatabase {
	/**
	 * Current schema version for the role tables.
	 *
	 * @since Unreleased Defines the initial role schema version.
	 */
	private const SCHEMA_VERSION = '1';

	/**
	 * Option key used to persist the installed role schema version.
	 *
	 * @since Unreleased Stores the role schema version.
	 */
	private const SCHEMA_VERSION_OPTION = 'scouting_oidc_roles_schema_version';

	/**
	 * Ensures the role tables exist and are current after plugin updates.
	 *
	 * @since Unreleased Creates role tables after plugin updates.
	 */
	public function scouting_oidc_roles_database_maybe_upgrade(): void {
		$tables            = $this->scouting_oidc_roles_database_get_table_names();
		$installed_version = get_option( self::SCHEMA_VERSION_OPTION, '' );

		if ( self::SCHEMA_VERSION === $installed_version && $this->scouting_oidc_roles_database_tables_exist( $tables ) ) {
			return;
		}

		$this->scouting_oidc_roles_database_create();
	}

	/**
	 * Creates or updates the tables used to store Scouts Online roles.
	 *
	 * @since Unreleased Creates and updates role tables.
	 */
	public function scouting_oidc_roles_database_create(): void {
		global $wpdb;

		$tables          = $this->scouting_oidc_roles_database_get_table_names();
		$charset_collate = $wpdb->get_charset_collate();

		$organisations_sql = "CREATE TABLE {$tables['organisations']} (
			organisation_id VARCHAR(20) NOT NULL,
			name VARCHAR(255) NOT NULL,
			last_seen_at DATETIME NOT NULL,
			PRIMARY KEY  (organisation_id),
			KEY last_seen_at (last_seen_at)
		) $charset_collate;";

		$organisation_units_sql = "CREATE TABLE {$tables['organisation_units']} (
			organisation_unit_id BIGINT(20) UNSIGNED NOT NULL,
			organisation_id VARCHAR(20) NOT NULL,
			name VARCHAR(255) NOT NULL,
			unit_type VARCHAR(100) NOT NULL,
			game_section_type VARCHAR(100) NULL,
			last_seen_at DATETIME NOT NULL,
			PRIMARY KEY  (organisation_unit_id),
			KEY organisation_id (organisation_id),
			KEY last_seen_at (last_seen_at)
		) $charset_collate;";

		$user_roles_sql = "CREATE TABLE {$tables['user_roles']} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			organisation_unit_id BIGINT(20) UNSIGNED NOT NULL,
			role_key CHAR(64) NOT NULL,
			role_name VARCHAR(255) NOT NULL,
			role_type VARCHAR(100) NOT NULL,
			member_type VARCHAR(100) NOT NULL,
			category VARCHAR(255) NULL,
			last_seen_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_unit_role (user_id, organisation_unit_id, role_key),
			KEY organisation_unit_id (organisation_unit_id),
			KEY last_seen_at (last_seen_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $organisations_sql );
		dbDelta( $organisation_units_sql );
		dbDelta( $user_roles_sql );

		update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Gets the database table names used for Scouts Online roles.
	 *
	 * @since Unreleased Gets role table names.
	 *
	 * @return array<string, string> Role table names keyed by purpose.
	 */
	private function scouting_oidc_roles_database_get_table_names(): array {
		global $wpdb;

		return array(
			'organisations'      => $wpdb->prefix . 'scouting_oidc_organisations',
			'organisation_units' => $wpdb->prefix . 'scouting_oidc_organisation_units',
			'user_roles'         => $wpdb->prefix . 'scouting_oidc_user_roles',
		);
	}

	/**
	 * Checks whether every role table exists.
	 *
	 * @since Unreleased Checks role table availability.
	 *
	 * @param array<string, string> $tables Role table names keyed by purpose.
	 * @return bool True when every table exists.
	 */
	private function scouting_oidc_roles_database_tables_exist( array $tables ): bool {
		global $wpdb;

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

			if ( $table !== $found_table ) {
				return false;
			}
		}

		return true;
	}
}
