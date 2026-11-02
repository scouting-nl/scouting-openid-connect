<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.7.0 Adds SOL role authorization.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Combines SOL role mappings and reconciles plugin-managed WordPress roles.
 *
 * @since 2.7.0 Adds SOL role authorization.
 */
class RolesAuthorization {
	/**
	 * Option storing SOL role mapping rules.
	 *
	 * @since 2.7.0 Reads SOL role mapping rules.
	 */
	private const RULES_OPTION = 'scouting_oidc_sol_role_mapping_rules';

	/**
	 * User meta key tracking roles added rather than already present.
	 *
	 * @since 2.7.0 Tracks ownership for safe role removal and uninstall.
	 */
	private const OWNED_ROLE_META_KEY = 'scouting_oidc_owned_wordpress_role';

	/**
	 * Pattern for a six-digit Scouts Online organisation identifier.
	 *
	 * @since 2.7.0 Validates SOL organisation identifiers.
	 */
	private const ORGANISATION_ID_PATTERN = '/^\d{6}$/';

	/**
	 * Active SOL assignments cached by WordPress user ID for the current request.
	 *
	 * @since 2.7.0 Avoids repeated authorization queries in a request.
	 *
	 * @var array<int, array<int, array{role_id: int, organisation_unit_id: int, organisation_id: string, organisation_ids: array<int, string>}>>
	 */
	private static array $active_assignments = array();

	/**
	 * Gets configured role mapping rules.
	 *
	 * @since 2.7.0 Normalizes SOL role mapping rules.
	 *
	 * @return array{rules: array} Mapping configuration with normalized selection lists.
	 */
	public static function scouting_oidc_roles_authorization_get_mapping(): array {
		$wordpress_roles = wp_roles()->roles;
		$stored_mapping  = get_option( self::RULES_OPTION, array() );
		$stored_rules    = is_array( $stored_mapping ) && isset( $stored_mapping['rules'] ) && is_array( $stored_mapping['rules'] ) ? $stored_mapping['rules'] : array();
		$rules           = array();
		foreach ( $stored_rules as $stored_rule ) {
			$rule = self::scouting_oidc_roles_authorization_normalize_rule( $stored_rule, $wordpress_roles );
			if ( null !== $rule ) {
				$rules[] = $rule;
			}
		}

		return array(
			'rules' => $rules,
		);
	}

	/**
	 * Combines all matching rules; any matching deny blocks login.
	 *
	 * @since 2.7.0 Evaluates SOL role mapping rules.
	 *
	 * @param int  $user_id WordPress user ID.
	 * @param bool $new_user Whether the account was created during this login.
	 * @return array{wordpress_roles: string[], login_allowed: bool} Roles and login decision.
	 */
	public static function scouting_oidc_roles_authorization_get_user_decision( int $user_id, bool $new_user = false ): array {
		$preview = self::scouting_oidc_roles_authorization_get_user_preview( $user_id, $new_user );
		if ( is_wp_error( $preview ) ) {
			return array(
				'wordpress_roles' => array(),
				'login_allowed'   => false,
			);
		}

		return $preview['decision'];
	}

	/**
	 * Previews the saved mapping without changing roles or login state.
	 *
	 * @since 2.7.0 Combines matching roles and optionally adds the WordPress default role; pauses saved rules when the roles scope is disabled.
	 *
	 * @param int  $user_id WordPress user ID.
	 * @param bool $new_user Whether to preview a newly created account.
	 * @return array{decision: array{wordpress_roles: string[], login_allowed: bool}, rule_indexes: int[], assignments: array}|\WP_Error Authorization preview or an unavailable-data/read error.
	 */
	public static function scouting_oidc_roles_authorization_get_user_preview( int $user_id, bool $new_user = false ): array|\WP_Error {
		$roles_enabled = self::scouting_oidc_roles_authorization_has_roles_scope();
		$mapping       = $roles_enabled ? self::scouting_oidc_roles_authorization_get_mapping() : array( 'rules' => array() );
		$assignments   = $roles_enabled ? self::scouting_oidc_roles_authorization_get_active_assignments( $user_id ) : array();
		if ( is_wp_error( $assignments ) ) {
			if ( 'sol_assignments_unavailable' !== $assignments->get_error_code() || ! empty( $mapping['rules'] ) ) {
				return $assignments;
			}
			$assignments = array();
		}

		$matched_roles = array();
		$rule_indexes  = array();
		$login_allowed = true;
		foreach ( $mapping['rules'] as $index => $rule ) {
			if ( self::scouting_oidc_roles_authorization_rule_matches_assignments( $rule, $assignments ) ) {
				$rule_indexes[] = $index;
				if ( ! $rule['login_allowed'] ) {
					$login_allowed = false;
				} else {
					$matched_roles[] = $rule['wordpress_role'];
				}
			}
		}

		if ( $login_allowed && ( ( empty( $rule_indexes ) && $new_user ) || ( ! empty( $matched_roles ) && get_option( 'scouting_oidc_roles_add_default_role', false ) ) ) ) {
			$wordpress_roles = wp_roles()->roles;
			$default_role    = get_option( 'default_role', 'subscriber' );
			if ( ! is_string( $default_role ) || ! isset( $wordpress_roles[ $default_role ] ) ) {
				Logger::error( LogComponent::ROLES, 'The configured WordPress default role is unavailable.', $user_id );
				return new \WP_Error( 'sol_default_role_unavailable', __( 'Your access level could not be updated. Please contact the site administrator.', 'scouting-openid-connect' ) );
			}
			$matched_roles[] = $default_role;
		}

		return array(
			'decision'     => array(
				'wordpress_roles' => $login_allowed ? array_values( array_unique( $matched_roles ) ) : array(),
				'login_allowed'   => $login_allowed,
			),
			'rule_indexes' => $rule_indexes,
			'assignments'  => $assignments,
		);
	}

	/**
	 * Checks whether the configured scopes request SOL role assignments.
	 *
	 * @since 2.7.0 Shares the roles-scope check with administration notices.
	 *
	 * @return bool Whether the roles scope is enabled.
	 */
	public static function scouting_oidc_roles_authorization_has_roles_scope(): bool {
		$scopes = get_option( 'scouting_oidc_scopes', '' );
		return is_string( $scopes ) && in_array( 'roles', preg_split( '/\s+/', trim( $scopes ) ), true );
	}

	/**
	 * Reads the list of plugin-owned roles.
	 *
	 * @since 2.7.0 Supports multiple positively owned WordPress roles and single-role values already stored by development builds.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string[]|\WP_Error Owned role slugs or an invalid tracking error.
	 */
	public static function scouting_oidc_roles_authorization_get_owned_roles( int $user_id ): array|\WP_Error {
		$stored = get_user_meta( $user_id, self::OWNED_ROLE_META_KEY, true );
		if ( '' === $stored ) {
			return array();
		}
		// Earlier development builds stored one positively owned role as a string.
		if ( is_string( $stored ) ) {
			$stored = array( $stored );
		}
		if ( ! is_array( $stored ) || count( array_filter( $stored, static fn( $role ): bool => is_string( $role ) && '' !== $role ) ) !== count( $stored ) ) {
			Logger::error( LogComponent::ROLES, 'Stored WordPress role ownership is invalid.', $user_id );
			return new \WP_Error( 'sol_role_ownership_invalid', __( 'Your access level could not be updated. Please contact the site administrator.', 'scouting-openid-connect' ) );
		}
		return array_values( array_unique( $stored ) );
	}

	/**
	 * Reconciles all plugin-managed WordPress roles for a user.
	 *
	 * Existing roles not previously assigned by this plugin are left intact.
	 *
	 * @since 2.7.0 Adds matching roles and removes obsolete owned roles without removing manual roles.
	 *
	 * @param int      $user_id WordPress user ID.
	 * @param string[] $wordpress_roles WordPress role slugs to apply.
	 * @return bool Whether the roles could be reconciled.
	 */
	public static function scouting_oidc_roles_authorization_apply_wordpress_roles( int $user_id, array $wordpress_roles ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}
		foreach ( $wordpress_roles as $role ) {
			if ( ! is_string( $role ) || ! isset( wp_roles()->roles[ $role ] ) ) {
				Logger::error( LogComponent::ROLES, 'A mapped WordPress role is unavailable.', $user_id );
				return false;
			}
		}
		$owned_roles = self::scouting_oidc_roles_authorization_get_owned_roles( $user_id );
		if ( is_wp_error( $owned_roles ) ) {
			return false;
		}
		foreach ( array_diff( $owned_roles, $wordpress_roles ) as $managed_role ) {
			$user->remove_role( $managed_role );
			$saved_user = get_userdata( $user_id );
			if ( ! $saved_user instanceof \WP_User || in_array( $managed_role, $saved_user->roles, true ) ) {
				Logger::error( LogComponent::ROLES, 'Could not remove the previous plugin-owned WordPress role.', $user_id );
				return false;
			}
			$owned_roles = array_values( array_diff( $owned_roles, array( $managed_role ) ) );
			update_user_meta( $user_id, self::OWNED_ROLE_META_KEY, $owned_roles );
			if ( get_user_meta( $user_id, self::OWNED_ROLE_META_KEY, true ) !== $owned_roles ) {
				Logger::error( LogComponent::ROLES, 'Could not update ownership of the mapped WordPress roles.', $user_id );
				return false;
			}
		}

		foreach ( array_unique( $wordpress_roles ) as $wordpress_role ) {
			if ( in_array( $wordpress_role, $user->roles, true ) ) {
				continue;
			}
			$owned_roles = array_values( array_unique( array_merge( $owned_roles, array( $wordpress_role ) ) ) );
			update_user_meta( $user_id, self::OWNED_ROLE_META_KEY, $owned_roles );
			if ( get_user_meta( $user_id, self::OWNED_ROLE_META_KEY, true ) !== $owned_roles ) {
				Logger::error( LogComponent::ROLES, 'Could not record ownership of the mapped WordPress role.', $user_id );
				return false;
			}
			$user->add_role( $wordpress_role );
			$saved_user = get_userdata( $user_id );
			if ( ! $saved_user instanceof \WP_User || ! in_array( $wordpress_role, $saved_user->roles, true ) ) {
				Logger::error( LogComponent::ROLES, 'Could not save the mapped WordPress role.', $user_id );
				return false;
			}
		}

		return true;
	}

	/**
	 * Gets active SOL assignments for a user.
	 *
	 * @since 2.7.0 Reads active SOL role assignments for authorization.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array{role_id: int, organisation_unit_id: int, organisation_id: string, organisation_ids: array<int, string>}>|\WP_Error Active assignments with organisation ancestry, or an unavailable-data/read error.
	 */
	public static function scouting_oidc_roles_authorization_get_active_assignments( int $user_id ): array|\WP_Error {
		if ( $user_id <= 0 ) {
			return array();
		}

		if ( ! self::scouting_oidc_roles_authorization_has_roles_scope() || 'yes' !== get_user_meta( $user_id, 'scouting_oidc_roles_available', true ) ) {
			Logger::warning( LogComponent::ROLES, 'SOL assignments are unavailable for authorization; enable the roles scope and complete a fresh OIDC login.', $user_id );
			return new \WP_Error( 'sol_assignments_unavailable', __( 'Your access level could not be updated. Please contact the site administrator.', 'scouting-openid-connect' ) );
		}

		if ( isset( self::$active_assignments[ $user_id ] ) ) {
			return self::$active_assignments[ $user_id ];
		}

		global $wpdb;

		$assignments_table = $wpdb->prefix . 'scouting_oidc_sol_role_assignments';
		$roles_table       = $wpdb->prefix . 'scouting_oidc_sol_roles';
		$units_table       = $wpdb->prefix . 'scouting_oidc_sol_organisation_units';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT assignment.role_id, role.organisation_unit_id, unit.organisation_id ' .
				'FROM %i AS assignment ' .
				'INNER JOIN %i AS role ON role.role_id = assignment.role_id ' .
				'INNER JOIN %i AS unit ON unit.organisation_unit_id = role.organisation_unit_id ' .
				'WHERE assignment.user_id = %d ' .
				'AND assignment.start_date <= UTC_TIMESTAMP(6) ' .
				'AND ( assignment.end_date IS NULL OR assignment.end_date >= UTC_TIMESTAMP(6) )',
				$assignments_table,
				$roles_table,
				$units_table,
				$user_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			Logger::error( LogComponent::ROLES, 'Could not read active SOL assignments; authorization cannot be evaluated.', $user_id );
			return new \WP_Error( 'sol_assignments_read_failed' );
		}

		$organisation_ids   = array_map( 'strval', array_column( $rows, 'organisation_id' ) );
		$organisation_paths = self::scouting_oidc_roles_authorization_get_organisation_paths( $organisation_ids );
		if ( is_wp_error( $organisation_paths ) ) {
			return $organisation_paths;
		}
		$assignments = array();
		foreach ( $rows as $row ) {
			$organisation_id = (string) $row['organisation_id'];
			$assignments[]   = array(
				'role_id'              => (int) $row['role_id'],
				'organisation_unit_id' => (int) $row['organisation_unit_id'],
				'organisation_id'      => $organisation_id,
				'organisation_ids'     => $organisation_paths[ $organisation_id ] ?? array( $organisation_id ),
			);
		}

		self::$active_assignments[ $user_id ] = $assignments;

		return $assignments;
	}

	/**
	 * Gets each organisation together with every reachable parent organisation.
	 *
	 * @since 2.7.0 Shares SOL organisation hierarchy matches with the mapping editor.
	 *
	 * @param array<int, string> $organisation_ids Direct organisation IDs.
	 * @return array<string, array<int, string>>|\WP_Error Organisation IDs keyed by direct organisation ID, or a database read error.
	 */
	public static function scouting_oidc_roles_authorization_get_organisation_paths( array $organisation_ids ): array|\WP_Error {
		$organisation_ids = self::scouting_oidc_roles_authorization_normalize_organisation_ids( $organisation_ids );
		if ( empty( $organisation_ids ) ) {
			return array();
		}

		$parents_by_child = self::scouting_oidc_roles_authorization_get_organisation_parents();
		if ( is_wp_error( $parents_by_child ) ) {
			return $parents_by_child;
		}
		$paths = array();
		foreach ( $organisation_ids as $organisation_id ) {
			$paths[ $organisation_id ] = self::scouting_oidc_roles_authorization_get_organisation_path( $organisation_id, $parents_by_child );
		}

		return $paths;
	}

	/**
	 * Normalizes organisation identifiers for hierarchy lookups.
	 *
	 * @since 2.7.0 Validates SOL organisation hierarchy identifiers.
	 *
	 * @param array<int, string> $organisation_ids Raw organisation IDs.
	 * @return array<int, string> Normalized organisation IDs.
	 */
	private static function scouting_oidc_roles_authorization_normalize_organisation_ids( array $organisation_ids ): array {
		$result = array();
		foreach ( $organisation_ids as $organisation_id ) {
			if ( 1 === preg_match( self::ORGANISATION_ID_PATTERN, $organisation_id ) && ! in_array( $organisation_id, $result, true ) ) {
				$result[] = $organisation_id;
			}
		}

		return $result;
	}

	/**
	 * Gets direct parent organisation IDs keyed by child organisation ID.
	 *
	 * @since 2.7.0 Reads SOL organisation hierarchy relationships.
	 *
	 * @return array<string, array<int, string>>|\WP_Error Parent IDs keyed by child organisation ID, or a database read error.
	 */
	private static function scouting_oidc_roles_authorization_get_organisation_parents(): array|\WP_Error {
		global $wpdb;

		$organisation_parents_table = $wpdb->prefix . 'scouting_oidc_sol_organisation_parents';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT child_organisation_id, parent_organisation_id FROM %i', $organisation_parents_table ), ARRAY_A );
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			Logger::error( LogComponent::ROLES, 'Could not read SOL organisation hierarchy; authorization cannot be evaluated.' );
			return new \WP_Error( 'sol_hierarchy_read_failed' );
		}
		$result = array();
		foreach ( $rows as $row ) {
			$child_organisation_id  = isset( $row['child_organisation_id'] ) ? (string) $row['child_organisation_id'] : '';
			$parent_organisation_id = isset( $row['parent_organisation_id'] ) ? (string) $row['parent_organisation_id'] : '';
			if ( 1 === preg_match( self::ORGANISATION_ID_PATTERN, $child_organisation_id ) && 1 === preg_match( self::ORGANISATION_ID_PATTERN, $parent_organisation_id ) ) {
				$result[ $child_organisation_id ][] = $parent_organisation_id;
			}
		}

		return $result;
	}

	/**
	 * Gets one organisation and all of its reachable parents.
	 *
	 * @since 2.7.0 Traverses SOL organisation hierarchy relationships.
	 *
	 * @param string                            $organisation_id Direct organisation ID.
	 * @param array<string, array<int, string>> $parents_by_child Parent IDs keyed by child organisation ID.
	 * @return array<int, string> Organisation and parent IDs.
	 */
	private static function scouting_oidc_roles_authorization_get_organisation_path( string $organisation_id, array $parents_by_child ): array {
		$path    = array( $organisation_id );
		$pending = array( $organisation_id );
		while ( ! empty( $pending ) ) {
			$current_organisation_id = array_pop( $pending );
			foreach ( $parents_by_child[ $current_organisation_id ] ?? array() as $parent_organisation_id ) {
				if ( ! in_array( $parent_organisation_id, $path, true ) ) {
					$path[]    = $parent_organisation_id;
					$pending[] = $parent_organisation_id;
				}
			}
		}

		return $path;
	}

	/**
	 * Clears only the request-local assignment cache, never persisted assignments.
	 *
	 * @since 2.7.0 Keeps same-request authorization data current.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public static function scouting_oidc_roles_authorization_clear_active_assignments_cache( int $user_id ): void {
		unset( self::$active_assignments[ $user_id ] );
	}

	/**
	 * Normalizes one stored role mapping rule.
	 *
	 * @since 2.7.0 Safely reads stored SOL role mapping rules.
	 *
	 * @param mixed                $stored_rule Stored rule value.
	 * @param array<string, array> $wordpress_roles Available WordPress roles.
	 * @return array|null Normalized rule with selection lists and operators, or null when invalid.
	 */
	private static function scouting_oidc_roles_authorization_normalize_rule( mixed $stored_rule, array $wordpress_roles ): ?array {
		if ( ! is_array( $stored_rule ) ) {
			return null;
		}

		$conditions = self::scouting_oidc_roles_authorization_normalize_conditions( $stored_rule );
		if ( is_wp_error( $conditions ) ) {
			return null;
		}
		$wordpress_role = isset( $stored_rule['wordpress_role'] ) && is_string( $stored_rule['wordpress_role'] ) ? sanitize_key( $stored_rule['wordpress_role'] ) : '';
		$id             = isset( $stored_rule['id'] ) && is_string( $stored_rule['id'] ) ? sanitize_text_field( $stored_rule['id'] ) : '';

		if ( ! isset( $wordpress_roles[ $wordpress_role ] ) ) {
			return null;
		}

		return array_merge(
			array( 'id' => $id ),
			$conditions,
			array(
				'wordpress_role' => $wordpress_role,
				'login_allowed'  => ! empty( $stored_rule['login_allowed'] ),
			)
		);
	}

	/**
	 * Normalizes inclusive/exclusive selection lists.
	 *
	 * @since 2.7.0 Supports multiple values per condition.
	 *
	 * @param array $input Rule conditions.
	 * @return array|\WP_Error Normalized conditions or invalid-input error.
	 */
	public static function scouting_oidc_roles_authorization_normalize_conditions( array $input ): array|\WP_Error {
		$conditions = array();
		$has_values = false;
		foreach ( array( 'organisation', 'organisation_unit', 'role' ) as $field ) {
			$key    = $field . '_ids';
			$values = $input[ $key ] ?? array();
			$mode   = $input[ $field . '_operator' ] ?? 'in';
			if ( ! is_array( $values ) || ! in_array( $mode, array( 'in', 'not_in' ), true ) ) {
				return new \WP_Error( 'invalid_rule' );
			}
			$normalized = array();
			foreach ( $values as $value ) {
				if ( 'organisation' === $field ) {
					if ( ! is_string( $value ) || 1 !== preg_match( self::ORGANISATION_ID_PATTERN, $value ) ) {
						return new \WP_Error( 'invalid_organisation' );
					}
					$normalized[] = $value;
				} else {
					$identifier = self::scouting_oidc_roles_authorization_normalize_positive_id( $value );
					if ( $identifier <= 0 ) {
						return new \WP_Error( 'invalid_rule' );
					}
					$normalized[] = $identifier;
				}
			}
			$normalized = array_values( array_unique( $normalized ) );
			sort( $normalized, 'organisation' === $field ? SORT_STRING : SORT_NUMERIC );
			$conditions[ $key ]                 = $normalized;
			$conditions[ $field . '_operator' ] = empty( $normalized ) ? 'in' : $mode;
			$has_values                         = $has_values || ! empty( $normalized );
		}
		return $has_values ? $conditions : new \WP_Error( 'invalid_rule' );
	}

	/**
	 * Checks all populated conditions against one active assignment at a time.
	 *
	 * @since 2.7.0 Matches role mapping conditions against active assignments.
	 *
	 * @param array $rule Normalized mapping rule.
	 * @param array $assignments Active assignments with organisation ancestry.
	 * @return bool Whether the rule matches.
	 */
	private static function scouting_oidc_roles_authorization_rule_matches_assignments( array $rule, array $assignments ): bool {
		foreach ( $assignments as $assignment ) {
			foreach ( array( 'organisation', 'organisation_unit', 'role' ) as $field ) {
				$selected = $rule[ $field . '_ids' ];
				if ( empty( $selected ) ) {
					continue;
				}
				$actual  = 'organisation' === $field ? $assignment['organisation_ids'] : array( $assignment[ $field . '_id' ] );
				$matches = ! empty( array_intersect( $selected, $actual ) );
				if ( ( 'in' === $rule[ $field . '_operator' ] && ! $matches ) || ( 'not_in' === $rule[ $field . '_operator' ] && $matches ) ) {
					continue 2;
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Normalizes a positive integer identifier or zero.
	 *
	 * @since 2.7.0 Reads optional role mapping identifiers.
	 *
	 * @param mixed $value Raw identifier.
	 * @return int Positive identifier or zero.
	 */
	private static function scouting_oidc_roles_authorization_normalize_positive_id( mixed $value ): int {
		if ( is_int( $value ) ) {
			return max( 0, $value );
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d+$/', $value ) ) {
			return 0;
		}

		$identifier = filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );

		return false === $identifier ? 0 : (int) $identifier;
	}
}
