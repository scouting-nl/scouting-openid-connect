<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.7.0 Adds UserInfo role synchronization.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronizes Scouts Online role data.
 *
 * @since 2.7.0 Adds UserInfo role synchronization.
 */
class RolesSync {
	/**
	 * Synchronizes the role graph from a validated UserInfo response.
	 *
	 * A missing role graph preserves stored data but makes it unavailable for
	 * authorization. An explicitly empty assignment list removes
	 * the user's previously synchronized SOL assignments.
	 *
	 * @since 2.7.0 Synchronizes UserInfo role claims.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $user_info Validated UserInfo response.
	 * @return bool Whether synchronization succeeded or optional role claims were absent.
	 */
	public static function scouting_oidc_roles_sync_user_info( int $user_id, array $user_info ): bool {
		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			Logger::warning( LogComponent::ROLES, 'Skipped SOL role synchronization because the WordPress user could not be found.', $user_id > 0 ? $user_id : null );
			return false;
		}

		RolesAuthorization::scouting_oidc_roles_authorization_clear_active_assignments_cache( $user_id );
		update_user_meta( $user_id, 'scouting_oidc_roles_available', 'no' );
		if ( 'no' !== get_user_meta( $user_id, 'scouting_oidc_roles_available', true ) ) {
			Logger::error( LogComponent::ROLES, 'Could not invalidate stored SOL assignments before synchronization.', $user_id );
			return false;
		}

		$role_graph = self::scouting_oidc_roles_sync_normalize_role_graph( $user_info );
		if ( null === $role_graph ) {
			Logger::warning( LogComponent::ROLES, 'SOL role data is unavailable; stored assignments cannot authorize access.', $user_id );
			return true;
		}

		if ( is_wp_error( $role_graph ) ) {
			Logger::warning( LogComponent::ROLES, 'Skipped SOL role synchronization because the UserInfo role graph was invalid.', $user_id );
			return false;
		}

		if ( ! self::scouting_oidc_roles_sync_persist_role_graph( $user_id, $role_graph ) ) {
			Logger::error( LogComponent::ROLES, 'SOL role synchronization could not be saved.', $user_id );
			return false;
		}

		update_user_meta( $user_id, 'scouting_oidc_roles_available', 'yes' );
		if ( 'yes' !== get_user_meta( $user_id, 'scouting_oidc_roles_available', true ) ) {
			Logger::error( LogComponent::ROLES, 'Could not mark synchronized SOL assignments as available.', $user_id );
			return false;
		}
		Logger::debug( LogComponent::ROLES, 'SOL role synchronization completed.', $user_id );
		return true;
	}

	/**
	 * Deletes role assignments for a deleted user.
	 *
	 * @since 2.7.0 Deletes synchronized role data with the WordPress user.
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
			$wpdb->prefix . 'scouting_oidc_sol_role_assignments',
			array( 'user_id' => $user_id ),
			array( '%d' )
		);
	}

	/**
	 * Normalizes the role graph supplied by UserInfo.
	 *
	 * @since 2.7.0 Validates UserInfo role claims.
	 *
	 * @param array $user_info Validated UserInfo response.
	 * @return array|\WP_Error|null Normalized role graph, invalid-data error, or null when role claims are absent.
	 */
	private static function scouting_oidc_roles_sync_normalize_role_graph( array $user_info ): array|\WP_Error|null {
		$required_claims = array( 'organisations', 'organisation_units', 'roles', 'role_assignments' );
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

		$role_assignments = self::scouting_oidc_roles_sync_normalize_role_assignments( $user_info['role_assignments'], $roles );
		if ( is_wp_error( $role_assignments ) ) {
			return $role_assignments;
		}

		return array(
			'organisations'      => $organisations,
			'organisation_units' => $organisation_units,
			'roles'              => $roles,
			'role_assignments'   => $role_assignments,
		);
	}

	/**
	 * Normalizes organisations from UserInfo.
	 *
	 * @since 2.7.0 Normalizes UserInfo organisations.
	 *
	 * @param array $raw_organisations Raw UserInfo organisations claim.
	 * @return array<string, array{organisation_id: string, name: string, parent_ids: array<int, string>, has_parent_data: bool}>|\WP_Error Normalized organisations, or an error.
	 */
	private static function scouting_oidc_roles_sync_normalize_organisations( array $raw_organisations ): array|\WP_Error {
		$organisations = array();
		$parent_names  = array();

		foreach ( $raw_organisations as $raw_organisation ) {
			if ( ! is_array( $raw_organisation ) ) {
				return new \WP_Error( 'invalid_organisation' );
			}

			$organisation_id = self::scouting_oidc_roles_sync_normalize_organisation_id( $raw_organisation['organisation_id'] ?? $raw_organisation['id'] ?? null );
			$name            = self::scouting_oidc_roles_sync_normalize_text( $raw_organisation['name'] ?? null, 255 );
			if ( null === $organisation_id || null === $name ) {
				return new \WP_Error( 'invalid_organisation' );
			}

			if ( isset( $organisations[ $organisation_id ] ) ) {
				return new \WP_Error( 'duplicate_organisation' );
			}

			$has_parent_data = array_key_exists( 'parents', $raw_organisation ) && null !== $raw_organisation['parents'];
			$parent_ids      = array();
			if ( $has_parent_data ) {
				if ( ! is_array( $raw_organisation['parents'] ) ) {
					return new \WP_Error( 'invalid_organisation_parent' );
				}

				foreach ( $raw_organisation['parents'] as $raw_parent ) {
					if ( ! is_array( $raw_parent ) ) {
						return new \WP_Error( 'invalid_organisation_parent' );
					}

					$parent_id   = self::scouting_oidc_roles_sync_normalize_organisation_id( $raw_parent['organisation_id'] ?? $raw_parent['id'] ?? null );
					$parent_name = self::scouting_oidc_roles_sync_normalize_text( $raw_parent['name'] ?? null, 255 );
					if ( null === $parent_id || null === $parent_name || $organisation_id === $parent_id || in_array( $parent_id, $parent_ids, true ) ) {
						return new \WP_Error( 'invalid_organisation_parent' );
					}

					if ( isset( $parent_names[ $parent_id ] ) && $parent_names[ $parent_id ] !== $parent_name ) {
						return new \WP_Error( 'inconsistent_organisation_parent' );
					}

					$parent_ids[]               = $parent_id;
					$parent_names[ $parent_id ] = $parent_name;
				}
			}

			$organisations[ $organisation_id ] = array(
				'organisation_id' => $organisation_id,
				'name'            => $name,
				'parent_ids'      => $parent_ids,
				'has_parent_data' => $has_parent_data,
			);
		}

		foreach ( $parent_names as $parent_id => $parent_name ) {
			if ( isset( $organisations[ $parent_id ] ) ) {
				if ( $organisations[ $parent_id ]['name'] !== $parent_name ) {
					return new \WP_Error( 'inconsistent_organisation_parent' );
				}
				continue;
			}

			$organisations[ $parent_id ] = array(
				'organisation_id' => $parent_id,
				'name'            => $parent_name,
				'parent_ids'      => array(),
				'has_parent_data' => false,
			);
		}

		return $organisations;
	}

	/**
	 * Normalizes organisation units from UserInfo.
	 *
	 * @since 2.7.0 Normalizes UserInfo organisation units.
	 *
	 * @param array                                                                                                              $raw_organisation_units Raw UserInfo organisation units claim.
	 * @param array<string, array{organisation_id: string, name: string, parent_ids: array<int, string>, has_parent_data: bool}> $organisations Normalized organisations.
	 * @return array<int, array{organisation_id: string, name: string, unit_type: string, game_section_type: string|null}>|\WP_Error Normalized organisation units, or an error.
	 */
	private static function scouting_oidc_roles_sync_normalize_organisation_units( array $raw_organisation_units, array $organisations ): array|\WP_Error {
		$organisation_units = array();

		foreach ( $raw_organisation_units as $array_key => $raw_organisation_unit ) {
			if ( ! is_array( $raw_organisation_unit ) ) {
				return new \WP_Error( 'invalid_organisation_unit' );
			}

			$organisation_unit_id = self::scouting_oidc_roles_sync_normalize_positive_id( $raw_organisation_unit['id'] ?? $array_key );
			$organisation_id      = self::scouting_oidc_roles_sync_normalize_organisation_id( $raw_organisation_unit['organisation_id'] ?? null );
			$name                 = self::scouting_oidc_roles_sync_normalize_text( $raw_organisation_unit['name'] ?? null, 255 );
			$unit_type            = self::scouting_oidc_roles_sync_normalize_text( $raw_organisation_unit['type'] ?? null, 255 );
			$game_section_type    = null;
			if ( array_key_exists( 'game_section_type', $raw_organisation_unit ) && null !== $raw_organisation_unit['game_section_type'] ) {
				$game_section_type = self::scouting_oidc_roles_sync_normalize_text( $raw_organisation_unit['game_section_type'], 255 );
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
	 * Normalizes role definitions from UserInfo.
	 *
	 * @since 2.7.0 Normalizes UserInfo role assignments.
	 *
	 * @param array $raw_roles Raw UserInfo roles claim.
	 * @param array $organisation_units Normalized organisation units.
	 * @return array<int, array{id: int, organisation_unit_id: int, role_name: string, role_type: string, member_type: string|null, category: string|null}>|\WP_Error Normalized roles keyed by source ID, or an error.
	 */
	private static function scouting_oidc_roles_sync_normalize_roles( array $raw_roles, array $organisation_units ): array|\WP_Error {
		$roles = array();

		foreach ( $raw_roles as $raw_role ) {
			if ( ! is_array( $raw_role ) ) {
				return new \WP_Error( 'invalid_role' );
			}

			$role_id              = self::scouting_oidc_roles_sync_normalize_positive_id( $raw_role['id'] ?? null );
			$organisation_unit_id = self::scouting_oidc_roles_sync_normalize_positive_id( $raw_role['organisation_unit_id'] ?? null );
			$role_name            = self::scouting_oidc_roles_sync_normalize_text( $raw_role['name'] ?? null, 255 );
			$role_type            = self::scouting_oidc_roles_sync_normalize_text( $raw_role['type'] ?? null, 255 );
			$member_type          = null;
			if ( array_key_exists( 'member_type', $raw_role ) && null !== $raw_role['member_type'] ) {
				$member_type = self::scouting_oidc_roles_sync_normalize_text( $raw_role['member_type'], 255 );
			}
			$has_invalid_member_type = array_key_exists( 'member_type', $raw_role ) && null !== $raw_role['member_type'] && null === $member_type;

			$category = null;
			if ( array_key_exists( 'category', $raw_role ) && null !== $raw_role['category'] ) {
				$category = self::scouting_oidc_roles_sync_normalize_text( $raw_role['category'], 255 );
			}
			$has_invalid_category = array_key_exists( 'category', $raw_role ) && null !== $raw_role['category'] && null === $category;

			if ( null === $role_id || null === $organisation_unit_id || null === $role_name || null === $role_type || $has_invalid_member_type || $has_invalid_category || ! isset( $organisation_units[ $organisation_unit_id ] ) ) {
				return new \WP_Error( 'invalid_role' );
			}

			if ( isset( $roles[ $role_id ] ) ) {
				return new \WP_Error( 'duplicate_role' );
			}

			$roles[ $role_id ] = array(
				'id'                   => $role_id,
				'organisation_unit_id' => $organisation_unit_id,
				'role_name'            => $role_name,
				'role_type'            => $role_type,
				'member_type'          => $member_type,
				'category'             => $category,
			);
		}

		return $roles;
	}

	/**
	 * Validates dated user assignments against the role definitions.
	 *
	 * @since 2.7.0 Stores source role assignment dates.
	 *
	 * @param array $raw_assignments Raw UserInfo assignments.
	 * @param array $roles Normalized roles keyed by source ID.
	 * @return array<int, array{role_id: int, start_date: string, end_date: string|null}>|\WP_Error Normalized assignments or an error.
	 */
	private static function scouting_oidc_roles_sync_normalize_role_assignments( array $raw_assignments, array $roles ): array|\WP_Error {
		$assignments = array();
		$seen        = array();

		foreach ( $raw_assignments as $raw_assignment ) {
			if ( ! is_array( $raw_assignment ) ) {
				return new \WP_Error( 'invalid_role_assignment' );
			}

			$role_id    = self::scouting_oidc_roles_sync_normalize_positive_id( $raw_assignment['role_id'] ?? null );
			$start_date = self::scouting_oidc_roles_sync_normalize_datetime( $raw_assignment['start_date'] ?? null );
			$end_date   = null;
			if ( array_key_exists( 'end_date', $raw_assignment ) && null !== $raw_assignment['end_date'] ) {
				$end_date = self::scouting_oidc_roles_sync_normalize_datetime( $raw_assignment['end_date'], true );
				if ( null === $end_date ) {
					return new \WP_Error( 'invalid_role_assignment' );
				}
			}

			if ( null === $role_id || ! isset( $roles[ $role_id ] ) || null === $start_date || ( null !== $end_date && $end_date < $start_date ) ) {
				return new \WP_Error( 'invalid_role_assignment' );
			}

			$key = $role_id . ':' . $start_date;
			if ( isset( $seen[ $key ] ) ) {
				return new \WP_Error( 'duplicate_role_assignment' );
			}

			$seen[ $key ]  = true;
			$assignments[] = array(
				'role_id'    => $role_id,
				'start_date' => $start_date,
				'end_date'   => $end_date,
			);
		}

		return $assignments;
	}

	/**
	 * Normalizes a Netherlands date or RFC 3339 timestamp to a UTC database timestamp.
	 *
	 * @since 2.7.0 Interprets date-only assignment dates in the Europe/Amsterdam timezone and keeps end dates inclusive.
	 *
	 * @param mixed $value Raw date or date-time.
	 * @param bool  $inclusive_end_date Whether a date-only value represents an inclusive end date.
	 * @return string|null UTC timestamp or null.
	 */
	private static function scouting_oidc_roles_sync_normalize_datetime( mixed $value, bool $inclusive_end_date = false ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}

		$is_date_only = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
		if ( ! $is_date_only && 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/', $value ) ) {
			return null;
		}

		try {
			$date = $is_date_only
				? new \DateTimeImmutable( $value, new \DateTimeZone( 'Europe/Amsterdam' ) )
				: new \DateTimeImmutable( $value );
		} catch ( \Exception $error ) {
			return null;
		}

		if ( ( $is_date_only && $date->format( 'Y-m-d' ) !== $value ) || ( ! $is_date_only && $date->format( 'Y-m-d\TH:i:s' ) !== substr( $value, 0, 19 ) ) ) {
			return null;
		}

		if ( $is_date_only && $inclusive_end_date ) {
			$date = $date->modify( '+1 day' )->modify( '-1 microsecond' );
		}

		return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s.u' );
	}

	/**
	 * Validates a six-digit Scouts Online organisation identifier.
	 *
	 * @since 2.7.0 Preserves leading zeroes in organisation IDs.
	 *
	 * @param mixed $value Raw organisation identifier.
	 * @return string|null Identifier or null when invalid.
	 */
	private static function scouting_oidc_roles_sync_normalize_organisation_id( mixed $value ): ?string {
		return is_string( $value ) && 1 === preg_match( '/^\d{6}$/', $value ) ? $value : null;
	}

	/**
	 * Persists a normalized role graph for a WordPress user.
	 *
	 * @since 2.7.0 Persists UserInfo role claims.
	 *
	 * @param int   $user_id WordPress user ID.
	 * @param array $role_graph Normalized role graph with dated assignments.
	 * @return bool Whether the role graph was persisted.
	 */
	private static function scouting_oidc_roles_sync_persist_role_graph( int $user_id, array $role_graph ): bool {
		global $wpdb;

		$organisations_table        = $wpdb->prefix . 'scouting_oidc_sol_organisations';
		$organisation_parents_table = $wpdb->prefix . 'scouting_oidc_sol_organisation_parents';
		$organisation_units_table   = $wpdb->prefix . 'scouting_oidc_sol_organisation_units';
		$roles_table                = $wpdb->prefix . 'scouting_oidc_sol_roles';
		$assignments_table          = $wpdb->prefix . 'scouting_oidc_sol_role_assignments';
		$current_time               = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transaction_started = $wpdb->query( 'START TRANSACTION' );
		if ( false === $transaction_started ) {
			return false;
		}

		if ( ! self::scouting_oidc_roles_sync_persist_organisations( $organisations_table, $organisation_parents_table, $role_graph['organisations'], $current_time ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		foreach ( $role_graph['organisation_units'] as $organisation_unit_id => $organisation_unit ) {
			if ( ! self::scouting_oidc_roles_sync_upsert_organisation_unit( $organisation_units_table, (int) $organisation_unit_id, $organisation_unit, $current_time ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}

		foreach ( $role_graph['roles'] as $role ) {
			if ( ! self::scouting_oidc_roles_sync_upsert_role( $roles_table, $role, $current_time ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( $assignments_table, array( 'user_id' => $user_id ), array( '%d' ) );
		if ( false === $deleted ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		foreach ( $role_graph['role_assignments'] as $assignment ) {
			if ( ! self::scouting_oidc_roles_sync_insert_assignment( $assignments_table, $user_id, $assignment, $current_time ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
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
	 * Persists organisations and any optional parent relationships.
	 *
	 * @since 2.7.0 Synchronizes UserInfo organisation hierarchies.
	 *
	 * @param string                                                                                                             $organisations_table Full organisations table name.
	 * @param string                                                                                                             $organisation_parents_table Full organisation parent table name.
	 * @param array<string, array{organisation_id: string, name: string, parent_ids: array<int, string>, has_parent_data: bool}> $organisations Normalized organisations.
	 * @param string                                                                                                             $current_time UTC timestamp.
	 * @return bool Whether organisations and parent relationships were saved.
	 */
	private static function scouting_oidc_roles_sync_persist_organisations( string $organisations_table, string $organisation_parents_table, array $organisations, string $current_time ): bool {
		foreach ( $organisations as $organisation ) {
			if ( ! self::scouting_oidc_roles_sync_upsert_organisation( $organisations_table, $organisation, $current_time ) ) {
				return false;
			}
		}

		foreach ( $organisations as $organisation ) {
			if ( $organisation['has_parent_data'] && ! self::scouting_oidc_roles_sync_replace_organisation_parents( $organisation_parents_table, $organisation, $current_time ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Upserts a synchronized organisation.
	 *
	 * @since 2.7.0 Saves synchronized organisations.
	 *
	 * @param string                                                                                              $organisations_table Full organisations table name.
	 * @param array{organisation_id: string, name: string, parent_ids: array<int, string>, has_parent_data: bool} $organisation Normalized organisation.
	 * @param string                                                                                              $current_time UTC timestamp.
	 * @return bool Whether the organisation was saved.
	 */
	private static function scouting_oidc_roles_sync_upsert_organisation( string $organisations_table, array $organisation, string $current_time ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (organisation_id, name, last_seen_at) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE name = %s, last_seen_at = %s',
				$organisations_table,
				$organisation['organisation_id'],
				$organisation['name'],
				$current_time,
				$organisation['name'],
				$current_time
			)
		);

		return false !== $result;
	}

	/**
	 * Replaces the known parent relationships for an organisation.
	 *
	 * @since 2.7.0 Synchronizes optional UserInfo organisation parents.
	 *
	 * @param string                                                                                              $organisation_parents_table Full organisation parent table name.
	 * @param array{organisation_id: string, name: string, parent_ids: array<int, string>, has_parent_data: bool} $organisation Normalized organisation.
	 * @param string                                                                                              $current_time UTC timestamp.
	 * @return bool Whether parent relationships were saved.
	 */
	private static function scouting_oidc_roles_sync_replace_organisation_parents( string $organisation_parents_table, array $organisation, string $current_time ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete( $organisation_parents_table, array( 'child_organisation_id' => $organisation['organisation_id'] ), array( '%s' ) );
		if ( false === $deleted ) {
			return false;
		}

		foreach ( $organisation['parent_ids'] as $parent_organisation_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$inserted = $wpdb->insert(
				$organisation_parents_table,
				array(
					'child_organisation_id'  => $organisation['organisation_id'],
					'parent_organisation_id' => $parent_organisation_id,
					'last_seen_at'           => $current_time,
				),
				array( '%s', '%s', '%s' )
			);
			if ( false === $inserted ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Upserts a synchronized organisation unit.
	 *
	 * @since 2.7.0 Saves synchronized organisation units.
	 *
	 * @param string                                                                                          $organisation_units_table Full organisation units table name.
	 * @param int                                                                                             $organisation_unit_id Organisation unit ID.
	 * @param array{organisation_id: string, name: string, unit_type: string, game_section_type: string|null} $organisation_unit Normalized organisation unit.
	 * @param string                                                                                          $current_time UTC timestamp.
	 * @return bool Whether the organisation unit was saved.
	 */
	private static function scouting_oidc_roles_sync_upsert_organisation_unit( string $organisation_units_table, int $organisation_unit_id, array $organisation_unit, string $current_time ): bool {
		global $wpdb;
		$game_section_type = $organisation_unit['game_section_type'] ?? '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (organisation_unit_id, organisation_id, name, unit_type, game_section_type, last_seen_at) VALUES (%d, %s, %s, %s, NULLIF(%s, \'\'), %s) ON DUPLICATE KEY UPDATE organisation_id = %s, name = %s, unit_type = %s, game_section_type = NULLIF(%s, \'\'), last_seen_at = %s',
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
	 * @since 2.7.0 Saves shared synchronized roles.
	 *
	 * @param string $roles_table Full roles table name.
	 * @param array  $role Normalized role.
	 * @param string $current_time UTC timestamp.
	 * @return bool Whether the role was saved.
	 */
	private static function scouting_oidc_roles_sync_upsert_role( string $roles_table, array $role, string $current_time ): bool {
		global $wpdb;
		$category    = $role['category'] ?? '';
		$member_type = $role['member_type'] ?? '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (role_id, organisation_unit_id, role_name, role_type, member_type, category, last_seen_at) VALUES (%d, %d, %s, %s, NULLIF(%s, \'\'), NULLIF(%s, \'\'), %s) ON DUPLICATE KEY UPDATE organisation_unit_id = %d, role_name = %s, role_type = %s, member_type = NULLIF(%s, \'\'), category = NULLIF(%s, \'\'), last_seen_at = %s',
				$roles_table,
				$role['id'],
				$role['organisation_unit_id'],
				$role['role_name'],
				$role['role_type'],
				$member_type,
				$category,
				$current_time,
				$role['organisation_unit_id'],
				$role['role_name'],
				$role['role_type'],
				$member_type,
				$category,
				$current_time
			)
		);

		return false !== $result;
	}

	/**
	 * Inserts a dated role assignment for a WordPress user.
	 *
	 * @since 2.7.0 Saves normalized user role assignments.
	 *
	 * @param string $assignments_table Full assignments table name.
	 * @param int    $user_id WordPress user ID.
	 * @param array  $assignment Validated role assignment.
	 * @param string $current_time UTC timestamp.
	 * @return bool Whether the assignment was saved.
	 */
	private static function scouting_oidc_roles_sync_insert_assignment( string $assignments_table, int $user_id, array $assignment, string $current_time ): bool {
		global $wpdb;
		$end_date = $assignment['end_date'] ?? '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (user_id, role_id, start_date, end_date, last_seen_at) VALUES (%d, %d, %s, NULLIF(%s, \'\'), %s)',
				$assignments_table,
				$user_id,
				$assignment['role_id'],
				$assignment['start_date'],
				$end_date,
				$current_time
			)
		);

		return false !== $result;
	}

	/**
	 * Normalizes a positive integer identifier.
	 *
	 * @since 2.7.0 Validates source identifiers.
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
	 * @since 2.7.0 Validates required role graph text.
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
