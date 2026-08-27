<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since Unreleased Adds UserInfo role synchronization.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronizes Scouts Online role data.
 *
 * @since Unreleased Adds UserInfo role synchronization.
 */
class RolesSync {
	/**
	 * Synchronizes the role graph from a validated UserInfo response.
	 *
	 * A missing role graph is treated as an unavailable optional claim and does
	 * not change stored role data. An explicitly empty role list is a valid role
	 * snapshot and removes the user's previously synchronized SOL roles.
	 *
	 * @since Unreleased Synchronizes UserInfo role claims.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $user_info Validated UserInfo response.
	 */
	public static function scouting_oidc_roles_sync_user_info( int $user_id, array $user_info ): void {
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			Logger::warning( LogComponent::ROLES, 'Skipped SOL role synchronization because the WordPress user could not be found.', $user_id > 0 ? $user_id : null );
			return;
		}

		$role_graph = self::scouting_oidc_roles_sync_normalize_role_graph( $user_info );
		if ( null === $role_graph ) {
			Logger::debug( LogComponent::ROLES, 'Skipped SOL role synchronization because the UserInfo response did not include a role graph.', $user_id );
			return;
		}

		if ( is_wp_error( $role_graph ) ) {
			Logger::warning( LogComponent::ROLES, 'Skipped SOL role synchronization because the UserInfo role graph was invalid.', $user_id );
			return;
		}

		if ( ! self::scouting_oidc_roles_sync_persist_role_graph( $user_id, $role_graph ) ) {
			Logger::error( LogComponent::ROLES, 'SOL role synchronization could not be saved.', $user_id );
			return;
		}

		Logger::debug( LogComponent::ROLES, 'SOL role synchronization completed.', $user_id );
	}

	/**
	 * Deletes role assignments for a deleted user.
	 *
	 * @since Unreleased Deletes synchronized role data with the WordPress user.
	 *
	 * @param int $user_id WordPress user ID being deleted.
	 */
	public static function scouting_oidc_roles_sync_delete_user_roles( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'scouting_oidc_user_roles',
			array( 'user_id' => $user_id ),
			array( '%d' )
		);
	}

	/**
	 * Normalizes the role graph supplied by UserInfo.
	 *
	 * @since Unreleased Validates UserInfo role claims.
	 *
	 * @param array $user_info Validated UserInfo response.
	 * @return array{organisations: array<int, array{id: int, name: string}>, organisation_units: array<int, array{organisation_id: int, name: string, unit_type: string, game_section_type: string|null}>, roles: array<string, array{organisation_id: int, organisation_unit_id: int, role_key: string, role_name: string, role_type: string, member_type: string, category: string|null}>}|\WP_Error|null Normalized role graph, invalid-data error, or null when role claims are absent.
	 */
	private static function scouting_oidc_roles_sync_normalize_role_graph( array $user_info ): array|\WP_Error|null {
		$required_claims = array( 'organisations', 'organisation_units', 'roles' );
		foreach ( $required_claims as $claim ) {
			if ( ! array_key_exists( $claim, $user_info ) ) {
				return null;
			}

			if ( ! is_array( $user_info[ $claim ] ) ) {
				return new \WP_Error( 'invalid_role_claim' );
			}
		}

		$organisations = self::scouting_oidc_roles_sync_normalize_organisations( $user_info['organisations'] );
		if ( is_wp_error( $organisations ) ) {
			return $organisations;
		}

		$organisation_units = self::scouting_oidc_roles_sync_normalize_organisation_units( $user_info['organisation_units'], $organisations );
		if ( is_wp_error( $organisation_units ) ) {
			return $organisation_units;
		}

		$roles = self::scouting_oidc_roles_sync_normalize_roles( $user_info['roles'], $organisation_units );
		if ( is_wp_error( $roles ) ) {
			return $roles;
		}

		return array(
			'organisations'      => $organisations,
			'organisation_units' => $organisation_units,
			'roles'              => $roles,
		);
	}

	/**
	 * Normalizes organisations from UserInfo.
	 *
	 * @since Unreleased Normalizes UserInfo organisations.
	 *
	 * @param array $raw_organisations Raw UserInfo organisations claim.
	 * @return array<int, array{id: int, name: string}>|\WP_Error Normalized organisations, or an error.
	 */
	private static function scouting_oidc_roles_sync_normalize_organisations( array $raw_organisations ): array|\WP_Error {
		$organisations = array();

		foreach ( $raw_organisations as $array_key => $raw_organisation ) {
			if ( ! is_array( $raw_organisation ) ) {
				return new \WP_Error( 'invalid_organisation' );
			}

			$organisation_id = self::scouting_oidc_roles_sync_normalize_positive_id( $raw_organisation['id'] ?? $array_key );
			$name            = self::scouting_oidc_roles_sync_normalize_text( $raw_organisation['name'] ?? null, 255 );
			if ( null === $organisation_id || null === $name ) {
				return new \WP_Error( 'invalid_organisation' );
			}

			if ( isset( $organisations[ $organisation_id ] ) ) {
				return new \WP_Error( 'duplicate_organisation' );
			}

			$organisations[ $organisation_id ] = array(
				'id'   => $organisation_id,
				'name' => $name,
			);
		}

		return $organisations;
	}

	/**
	 * Normalizes organisation units from UserInfo.
	 *
	 * @since Unreleased Normalizes UserInfo organisation units.
	 *
	 * @param array                                    $raw_organisation_units Raw UserInfo organisation units claim.
	 * @param array<int, array{id: int, name: string}> $organisations Normalized organisations.
	 * @return array<int, array{organisation_id: int, name: string, unit_type: string, game_section_type: string|null}>|\WP_Error Normalized organisation units, or an error.
	 */
	private static function scouting_oidc_roles_sync_normalize_organisation_units( array $raw_organisation_units, array $organisations ): array|\WP_Error {
		$organisation_units = array();

		foreach ( $raw_organisation_units as $array_key => $raw_organisation_unit ) {
			if ( ! is_array( $raw_organisation_unit ) ) {
				return new \WP_Error( 'invalid_organisation_unit' );
			}

			$organisation_unit_id = self::scouting_oidc_roles_sync_normalize_positive_id( $raw_organisation_unit['id'] ?? $array_key );
			$organisation_id      = self::scouting_oidc_roles_sync_normalize_positive_id( $raw_organisation_unit['organisation_id'] ?? null );
			$name                 = self::scouting_oidc_roles_sync_normalize_text( $raw_organisation_unit['name'] ?? null, 255 );
			$unit_type            = self::scouting_oidc_roles_sync_normalize_text( $raw_organisation_unit['type'] ?? null, 100 );
			$game_section_type    = null;
			if ( array_key_exists( 'game_section_type', $raw_organisation_unit ) && null !== $raw_organisation_unit['game_section_type'] ) {
				$game_section_type = self::scouting_oidc_roles_sync_normalize_text( $raw_organisation_unit['game_section_type'], 100 );
			}
			$has_invalid_game_section_type = array_key_exists( 'game_section_type', $raw_organisation_unit ) && null !== $raw_organisation_unit['game_section_type'] && null === $game_section_type;

			if ( null === $organisation_unit_id || null === $organisation_id || null === $name || null === $unit_type || $has_invalid_game_section_type || ! isset( $organisations[ $organisation_id ] ) ) {
				return new \WP_Error( 'invalid_organisation_unit' );
			}

			if ( isset( $organisation_units[ $organisation_unit_id ] ) ) {
				return new \WP_Error( 'duplicate_organisation_unit' );
			}

			$organisation_units[ $organisation_unit_id ] = array(
				'organisation_id'   => $organisation_id,
				'name'              => $name,
				'unit_type'         => $unit_type,
				'game_section_type' => $game_section_type,
			);
		}

		return $organisation_units;
	}

	/**
	 * Normalizes user role assignments from UserInfo.
	 *
	 * @since Unreleased Normalizes UserInfo role assignments.
	 *
	 * @param array                                                                                                    $raw_roles Raw UserInfo roles claim.
	 * @param array<int, array{organisation_id: int, name: string, unit_type: string, game_section_type: string|null}> $organisation_units Normalized organisation units.
	 * @return array<string, array{organisation_id: int, organisation_unit_id: int, unit_type: string, game_section_type: string|null, role_key: string, role_name: string, role_type: string, member_type: string, category: string|null}>|\WP_Error Normalized roles keyed by role key, or an error.
	 */
	private static function scouting_oidc_roles_sync_normalize_roles( array $raw_roles, array $organisation_units ): array|\WP_Error {
		$roles = array();

		foreach ( $raw_roles as $raw_role ) {
			if ( ! is_array( $raw_role ) ) {
				return new \WP_Error( 'invalid_role' );
			}

			$organisation_unit_id = self::scouting_oidc_roles_sync_normalize_positive_id( $raw_role['organisation_unit_id'] ?? null );
			$role_name            = self::scouting_oidc_roles_sync_normalize_text( $raw_role['name'] ?? null, 255 );
			$role_type            = self::scouting_oidc_roles_sync_normalize_text( $raw_role['type'] ?? null, 100 );
			$member_type          = self::scouting_oidc_roles_sync_normalize_text( $raw_role['member_type'] ?? null, 100 );
			$category             = null;
			if ( array_key_exists( 'category', $raw_role ) && null !== $raw_role['category'] ) {
				$category = self::scouting_oidc_roles_sync_normalize_text( $raw_role['category'], 255 );
			}
			$has_invalid_category = array_key_exists( 'category', $raw_role ) && null !== $raw_role['category'] && null === $category;

			if ( null === $organisation_unit_id || null === $role_name || null === $role_type || null === $member_type || $has_invalid_category || ! isset( $organisation_units[ $organisation_unit_id ] ) ) {
				return new \WP_Error( 'invalid_role' );
			}

			$role_key = self::scouting_oidc_roles_sync_get_role_key( $organisation_unit_id, $role_name, $role_type, $member_type, $category );
			if ( isset( $roles[ $role_key ] ) ) {
				return new \WP_Error( 'duplicate_role' );
			}

			$roles[ $role_key ] = array(
				'organisation_id'      => $organisation_units[ $organisation_unit_id ]['organisation_id'],
				'organisation_unit_id' => $organisation_unit_id,
				'unit_type'            => $organisation_units[ $organisation_unit_id ]['unit_type'],
				'game_section_type'    => $organisation_units[ $organisation_unit_id ]['game_section_type'],
				'role_key'             => $role_key,
				'role_name'            => $role_name,
				'role_type'            => $role_type,
				'member_type'          => $member_type,
				'category'             => $category,
			);
		}

		return $roles;
	}

	/**
	 * Persists a normalized role graph for a WordPress user.
	 *
	 * @since Unreleased Persists UserInfo role claims.
	 *
	 * @param int                                                                                                                                                                                                                                                                                                                                                                            $user_id WordPress user ID.
	 * @param array{organisations: array<int, array{id: int, name: string}>, organisation_units: array<int, array{organisation_id: int, name: string, unit_type: string, game_section_type: string|null}>, roles: array<string, array{organisation_id: int, organisation_unit_id: int, role_key: string, role_name: string, role_type: string, member_type: string, category: string|null}>} $role_graph Normalized role graph.
	 * @return bool Whether the role graph was persisted.
	 */
	private static function scouting_oidc_roles_sync_persist_role_graph( int $user_id, array $role_graph ): bool {
		global $wpdb;

		$organisations_table      = $wpdb->prefix . 'scouting_oidc_sol_organisations';
		$organisation_units_table = $wpdb->prefix . 'scouting_oidc_sol_organisation_units';
		$roles_table              = $wpdb->prefix . 'scouting_oidc_sol_roles';
		$user_roles_table         = $wpdb->prefix . 'scouting_oidc_user_roles';
		$current_time             = current_time( 'mysql', true );
		$role_ids                 = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transaction_started = $wpdb->query( 'START TRANSACTION' );
		if ( false === $transaction_started ) {
			return false;
		}

		foreach ( $role_graph['organisations'] as $organisation ) {
			if ( ! self::scouting_oidc_roles_sync_upsert_organisation( $organisations_table, $organisation, $current_time ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}

		foreach ( $role_graph['organisation_units'] as $organisation_unit_id => $organisation_unit ) {
			if ( ! self::scouting_oidc_roles_sync_upsert_organisation_unit( $organisation_units_table, (int) $organisation_unit_id, $organisation_unit, $current_time ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}

		foreach ( $role_graph['roles'] as $role ) {
			$role_id = self::scouting_oidc_roles_sync_upsert_role( $roles_table, $role, $current_time );
			if ( null === $role_id || ! self::scouting_oidc_roles_sync_upsert_user_role( $user_roles_table, $user_id, $role_id, $current_time ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'ROLLBACK' );
				return false;
			}

			$role_ids[] = $role_id;
		}

		if ( ! self::scouting_oidc_roles_sync_delete_stale_user_roles( $user_roles_table, $user_id, $role_ids ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$committed = $wpdb->query( 'COMMIT' );
		if ( false === $committed ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		return true;
	}

	/**
	 * Upserts a synchronized organisation.
	 *
	 * @since Unreleased Saves synchronized organisations.
	 *
	 * @param string                       $organisations_table Full organisations table name.
	 * @param array{id: int, name: string} $organisation Normalized organisation.
	 * @param string                       $current_time UTC timestamp.
	 * @return bool Whether the organisation was saved.
	 */
	private static function scouting_oidc_roles_sync_upsert_organisation( string $organisations_table, array $organisation, string $current_time ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (organisation_id, name, last_seen_at) VALUES (%d, %s, %s) ON DUPLICATE KEY UPDATE name = %s, last_seen_at = %s',
				$organisations_table,
				$organisation['id'],
				$organisation['name'],
				$current_time,
				$organisation['name'],
				$current_time
			)
		);

		return false !== $result;
	}

	/**
	 * Upserts a synchronized organisation unit.
	 *
	 * @since Unreleased Saves synchronized organisation units.
	 *
	 * @param string                                                                                       $organisation_units_table Full organisation units table name.
	 * @param int                                                                                          $organisation_unit_id Organisation unit ID.
	 * @param array{organisation_id: int, name: string, unit_type: string, game_section_type: string|null} $organisation_unit Normalized organisation unit.
	 * @param string                                                                                       $current_time UTC timestamp.
	 * @return bool Whether the organisation unit was saved.
	 */
	private static function scouting_oidc_roles_sync_upsert_organisation_unit( string $organisation_units_table, int $organisation_unit_id, array $organisation_unit, string $current_time ): bool {
		global $wpdb;
		$game_section_type = $organisation_unit['game_section_type'] ?? '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (organisation_unit_id, organisation_id, name, unit_type, game_section_type, last_seen_at) VALUES (%d, %d, %s, %s, NULLIF(%s, \'\'), %s) ON DUPLICATE KEY UPDATE organisation_id = %d, name = %s, unit_type = %s, game_section_type = NULLIF(%s, \'\'), last_seen_at = %s',
				$organisation_units_table,
				$organisation_unit_id,
				$organisation_unit['organisation_id'],
				$organisation_unit['name'],
				$organisation_unit['unit_type'],
				$game_section_type,
				$current_time,
				$organisation_unit['organisation_id'],
				$organisation_unit['name'],
				$organisation_unit['unit_type'],
				$game_section_type,
				$current_time
			)
		);

		return false !== $result;
	}

	/**
	 * Upserts a shared synchronized role definition.
	 *
	 * @since Unreleased Saves shared synchronized roles.
	 *
	 * @param string                                                                                                                                                     $roles_table Full roles table name.
	 * @param array{organisation_id: int, organisation_unit_id: int, role_key: string, role_name: string, role_type: string, member_type: string, category: string|null} $role Normalized role.
	 * @param string                                                                                                                                                     $current_time UTC timestamp.
	 * @return int|null Saved role ID, or null on failure.
	 */
	private static function scouting_oidc_roles_sync_upsert_role( string $roles_table, array $role, string $current_time ): ?int {
		global $wpdb;
		$category = $role['category'] ?? '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (organisation_unit_id, role_key, role_name, role_type, member_type, category, last_seen_at) VALUES (%d, %s, %s, %s, %s, NULLIF(%s, \'\'), %s) ON DUPLICATE KEY UPDATE role_name = %s, role_type = %s, member_type = %s, category = NULLIF(%s, \'\'), last_seen_at = %s, role_id = LAST_INSERT_ID(role_id)',
				$roles_table,
				$role['organisation_unit_id'],
				$role['role_key'],
				$role['role_name'],
				$role['role_type'],
				$role['member_type'],
				$category,
				$current_time,
				$role['role_name'],
				$role['role_type'],
				$role['member_type'],
				$category,
				$current_time
			)
		);

		$role_id = (int) $wpdb->insert_id;

		return false !== $result && $role_id > 0 ? $role_id : null;
	}

	/**
	 * Upserts a synchronized role assignment for a WordPress user.
	 *
	 * @since Unreleased Saves normalized user role assignments.
	 *
	 * @param string $user_roles_table Full user roles table name.
	 * @param int    $user_id WordPress user ID.
	 * @param int    $role_id Shared role definition ID.
	 * @param string $current_time UTC timestamp.
	 * @return bool Whether the assignment was saved.
	 */
	private static function scouting_oidc_roles_sync_upsert_user_role( string $user_roles_table, int $user_id, int $role_id, string $current_time ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (user_id, role_id, last_seen_at) VALUES (%d, %d, %s) ON DUPLICATE KEY UPDATE last_seen_at = %s',
				$user_roles_table,
				$user_id,
				$role_id,
				$current_time,
				$current_time
			)
		);

		return false !== $result;
	}

	/**
	 * Deletes synchronized roles that are absent from the latest UserInfo snapshot.
	 *
	 * @since Unreleased Removes stale synchronized user roles.
	 *
	 * @param string          $user_roles_table Full user roles table name.
	 * @param int             $user_id WordPress user ID.
	 * @param array<int, int> $role_ids Current shared role IDs.
	 * @return bool Whether stale roles were removed.
	 */
	private static function scouting_oidc_roles_sync_delete_stale_user_roles( string $user_roles_table, int $user_id, array $role_ids ): bool {
		global $wpdb;

		if ( empty( $role_ids ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->delete( $user_roles_table, array( 'user_id' => $user_id ), array( '%d' ) );

			return false !== $result;
		}

		$placeholders = implode( ', ', array_fill( 0, count( $role_ids ), '%d' ) );
		$sql          = 'DELETE FROM %i WHERE user_id = %d AND role_id NOT IN (' . $placeholders . ')';
		$arguments    = array_merge( array( $user_roles_table, $user_id ), array_values( $role_ids ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->query( $wpdb->prepare( $sql, $arguments ) );

		return false !== $result;
	}

	/**
	 * Gets a stable role key when the provider supplies no role-assignment ID.
	 *
	 * @since Unreleased Derives role keys from source role fields.
	 *
	 * @param int         $organisation_unit_id Organisation unit ID.
	 * @param string      $role_name Role name.
	 * @param string      $role_type Role type.
	 * @param string      $member_type Member type.
	 * @param string|null $category Optional role category.
	 * @return string SHA-256 role key.
	 */
	private static function scouting_oidc_roles_sync_get_role_key( int $organisation_unit_id, string $role_name, string $role_type, string $member_type, ?string $category ): string {
		$role_data = wp_json_encode(
			array(
				'organisation_unit_id' => $organisation_unit_id,
				'role_name'            => $role_name,
				'role_type'            => $role_type,
				'member_type'          => $member_type,
				'category'             => $category,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		return hash( 'sha256', is_string( $role_data ) ? $role_data : '' );
	}

	/**
	 * Normalizes a positive integer identifier.
	 *
	 * @since Unreleased Validates source identifiers.
	 *
	 * @param mixed $value Raw numeric identifier.
	 * @return int|null Positive integer, or null when invalid.
	 */
	private static function scouting_oidc_roles_sync_normalize_positive_id( mixed $value ): ?int {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9]\d*$/', $value ) ) {
			return null;
		}

		$identifier = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );

		return false === $identifier ? null : (int) $identifier;
	}

	/**
	 * Normalizes required source text.
	 *
	 * @since Unreleased Validates required role graph text.
	 *
	 * @param mixed $value Raw source text.
	 * @param int   $maximum_length Maximum stored length.
	 * @return string|null Normalized text, or null when invalid.
	 */
	private static function scouting_oidc_roles_sync_normalize_text( mixed $value, int $maximum_length ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$value = sanitize_text_field( $value );
		if ( '' === $value || strlen( $value ) > $maximum_length ) {
			return null;
		}

		return $value;
	}
}
