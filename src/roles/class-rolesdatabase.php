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
	 * @since Unreleased Uses dedicated SOL source tables and normalized user assignments.
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
	 * @since Unreleased Creates the normalized role schema.
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
		) ENGINE=InnoDB $charset_collate;";

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
		) ENGINE=InnoDB $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $organisations_sql );
		dbDelta( $organisation_units_sql );
		dbDelta( $this->scouting_oidc_roles_database_get_roles_sql( $tables['roles'], $charset_collate ) );
		dbDelta( $this->scouting_oidc_roles_database_get_user_roles_sql( $tables['user_roles'], $charset_collate ) );

		foreach ( $tables as $transactional_table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB
			$engine_result = $wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ENGINE=InnoDB', $transactional_table ) );
			if ( false === $engine_result ) {
				return;
			}
		}

		update_option( self::SCHEMA_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Gets the shared role definition table SQL.
	 *
	 * @since Unreleased Defines normalized shared roles.
	 *
	 * @param string $table Full table name.
	 * @param string $charset_collate Database character set and collation SQL.
	 * @return string CREATE TABLE statement.
	 */
	private function scouting_oidc_roles_database_get_roles_sql( string $table, string $charset_collate ): string {
		return "CREATE TABLE $table (
			role_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			organisation_unit_id BIGINT(20) UNSIGNED NOT NULL,
			role_key CHAR(64) NOT NULL,
			role_name VARCHAR(255) NOT NULL,
			role_type VARCHAR(100) NOT NULL,
			member_type VARCHAR(100) NOT NULL,
			category VARCHAR(255) NULL,
			last_seen_at DATETIME NOT NULL,
			PRIMARY KEY  (role_id),
			UNIQUE KEY unit_role (organisation_unit_id, role_key),
			KEY role_key (role_key),
			KEY last_seen_at (last_seen_at)
		) ENGINE=InnoDB $charset_collate;";
	}

	/**
	 * Gets the user role assignment table SQL.
	 *
	 * @since Unreleased Defines normalized user role assignments.
	 *
	 * @param string $table Full table name.
	 * @param string $charset_collate Database character set and collation SQL.
	 * @return string CREATE TABLE statement.
	 */
	private function scouting_oidc_roles_database_get_user_roles_sql( string $table, string $charset_collate ): string {
		return "CREATE TABLE $table (
			user_id BIGINT(20) UNSIGNED NOT NULL,
			role_id BIGINT(20) UNSIGNED NOT NULL,
			last_seen_at DATETIME NOT NULL,
			PRIMARY KEY  (user_id, role_id),
			KEY role_id (role_id),
			KEY last_seen_at (last_seen_at)
		) ENGINE=InnoDB $charset_collate;";
	}

	/**
	 * Gets the current role-domain table names.
	 *
	 * @since Unreleased Gets normalized role table names.
	 *
	 * @return array<string, string> Role table names keyed by purpose.
	 */
	private function scouting_oidc_roles_database_get_table_names(): array {
		global $wpdb;

		return array(
			'organisations'      => $wpdb->prefix . 'scouting_oidc_sol_organisations',
			'organisation_units' => $wpdb->prefix . 'scouting_oidc_sol_organisation_units',
			'roles'              => $wpdb->prefix . 'scouting_oidc_sol_roles',
			'user_roles'         => $wpdb->prefix . 'scouting_oidc_user_roles',
		);
	}

	/**
	 * Checks whether every current role table exists.
	 *
	 * @since Unreleased Checks role table availability.
	 *
	 * @param array<string, string> $tables Role table names keyed by purpose.
	 * @return bool True when every table exists.
	 */
	private function scouting_oidc_roles_database_tables_exist( array $tables ): bool {
		foreach ( $tables as $table ) {
			if ( ! $this->scouting_oidc_roles_database_table_exists( $table ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks whether a database table exists.
	 *
	 * @since Unreleased Checks individual role tables.
	 *
	 * @param string $table Full table name.
	 * @return bool Whether the table exists.
	 */
	private function scouting_oidc_roles_database_table_exists( string $table ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

		return $table === $found_table;
	}
}
