<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since Unreleased Adds SOL role authorization.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Evaluates SOL role mappings and applies the plugin-managed WordPress role.
 *
 * @since Unreleased Adds SOL role authorization.
 */
class RolesAuthorization {
	/**
	 * Option storing ordered SOL role mapping rules.
	 *
	 * @since Unreleased Reads SOL role mapping rules.
	 */
	private const RULES_OPTION = 'scouting_oidc_sol_role_mapping_rules';

	/**
	 * User meta key tracking the WordPress role managed by this plugin.
	 *
	 * @since Unreleased Preserves independently managed WordPress roles.
	 */
	private const MANAGED_ROLE_META_KEY = 'scouting_oidc_managed_wordpress_role';

	/**
	 * Pattern for a six-digit Scouts Online organisation identifier.
	 *
	 * @since Unreleased Validates SOL organisation identifiers.
	 */
	private const ORGANISATION_ID_PATTERN = '/^\d{6}$/';

	/**
	 * Active SOL assignments cached by WordPress user ID for the current request.
	 *
	 * @since Unreleased Avoids repeated authorization queries in a request.
	 *
	 * @var array<int, array<int, array{role_id: int, organisation_unit_id: int, organisation_id: string, organisation_ids: array<int, string>}>>
	 */
	private static array $active_assignments = array();

	/**
	 * Gets the configured mapping with safe defaults for missing or stale data.
	 *
	 * @since Unreleased Normalizes SOL role mapping rules.
	 *
	 * @return array{rules: array<int, array{id: string, organisation_id: string, organisation_unit_id: int, role_id: int, wordpress_role: string, login_allowed: bool}>, default: array{wordpress_role: string, login_allowed: bool}} Mapping configuration.
	 */
	public static function scouting_oidc_roles_authorization_get_mapping(): array {
		$wordpress_roles = wp_roles()->roles;
		$default_role    = get_option( 'default_role', 'subscriber' );
		if ( ! is_string( $default_role ) || ! isset( $wordpress_roles[ $default_role ] ) ) {
			$default_role = isset( $wordpress_roles['subscriber'] ) ? 'subscriber' : (string) array_key_first( $wordpress_roles );
		}

		$stored_mapping = get_option( self::RULES_OPTION, array() );
		$stored_rules   = is_array( $stored_mapping ) && isset( $stored_mapping['rules'] ) && is_array( $stored_mapping['rules'] ) ? $stored_mapping['rules'] : array();
		$rules          = array();
		foreach ( $stored_rules as $stored_rule ) {
			$rule = self::scouting_oidc_roles_authorization_normalize_rule( $stored_rule, $wordpress_roles );
			if ( null !== $rule ) {
				$rules[] = $rule;
			}
		}

		$stored_default = is_array( $stored_mapping ) && isset( $stored_mapping['default'] ) && is_array( $stored_mapping['default'] ) ? $stored_mapping['default'] : array();
		if ( isset( $stored_default['wordpress_role'] ) && is_string( $stored_default['wordpress_role'] ) && isset( $wordpress_roles[ $stored_default['wordpress_role'] ] ) ) {
			$default_role = $stored_default['wordpress_role'];
		}

		return array(
			'rules'   => $rules,
			'default' => array(
				'wordpress_role' => $default_role,
				'login_allowed'  => ! isset( $stored_default['login_allowed'] ) || (bool) $stored_default['login_allowed'],
			),
		);
	}

	/**
	 * Gets the first matching authorization decision for a WordPress user.
	 *
	 * @since Unreleased Evaluates SOL role mapping rules.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array{wordpress_role: string, login_allowed: bool} Role and login decision.
	 */
	public static function scouting_oidc_roles_authorization_get_user_decision( int $user_id ): array {
		$mapping     = self::scouting_oidc_roles_authorization_get_mapping();
		$assignments = self::scouting_oidc_roles_authorization_get_active_assignments( $user_id );

		foreach ( $mapping['rules'] as $rule ) {
			if ( self::scouting_oidc_roles_authorization_rule_matches_assignments( $rule, $assignments ) ) {
				return array(
					'wordpress_role' => $rule['wordpress_role'],
					'login_allowed'  => $rule['login_allowed'],
				);
			}
		}

		return $mapping['default'];
	}

	/**
	 * Applies the current plugin-managed WordPress role for a user.
	 *
	 * Existing roles not previously assigned by this plugin are left intact.
	 *
	 * @since Unreleased Applies SOL role mappings without removing manual roles.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $wordpress_role WordPress role slug to apply.
	 * @return bool Whether the role could be applied.
	 */
	public static function scouting_oidc_roles_authorization_apply_wordpress_role( int $user_id, string $wordpress_role ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || ! isset( wp_roles()->roles[ $wordpress_role ] ) ) {
			return false;
		}

		$managed_role = get_user_meta( $user_id, self::MANAGED_ROLE_META_KEY, true );
		if ( is_string( $managed_role ) && '' !== $managed_role && $managed_role !== $wordpress_role ) {
			$user->remove_role( $managed_role );
		}

		if ( ! in_array( $wordpress_role, $user->roles, true ) ) {
			$user->add_role( $wordpress_role );
		}

		update_user_meta( $user_id, self::MANAGED_ROLE_META_KEY, $wordpress_role );

		return true;
	}

	/**
	 * Gets active SOL assignments for a user.
	 *
	 * @since Unreleased Reads active SOL role assignments for authorization.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array<int, array{role_id: int, organisation_unit_id: int, organisation_id: string, organisation_ids: array<int, string>}> Active assignments with organisation ancestry.
	 */
	public static function scouting_oidc_roles_authorization_get_active_assignments( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
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

		$organisation_ids   = array_map( 'strval', array_column( is_array( $rows ) ? $rows : array(), 'organisation_id' ) );
		$organisation_paths = self::scouting_oidc_roles_authorization_get_organisation_paths( $organisation_ids );
		$assignments        = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$organisation_id = (string) $row['organisation_id'];
			$assignments[] = array(
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
	 * @since Unreleased Expands SOL organisation hierarchy matches.
	 *
	 * @param array<int, string> $organisation_ids Direct organisation IDs.
	 * @return array<string, array<int, string>> Organisation IDs keyed by direct organisation ID.
	 */
	private static function scouting_oidc_roles_authorization_get_organisation_paths( array $organisation_ids ): array {
		$organisation_ids = self::scouting_oidc_roles_authorization_normalize_organisation_ids( $organisation_ids );
		if ( empty( $organisation_ids ) ) {
			return array();
		}

		$parents_by_child = self::scouting_oidc_roles_authorization_get_organisation_parents();
		$paths = array();
		foreach ( $organisation_ids as $organisation_id ) {
			$paths[ $organisation_id ] = self::scouting_oidc_roles_authorization_get_organisation_path( $organisation_id, $parents_by_child );
		}

		return $paths;
	}

	/**
	 * Normalizes organisation identifiers for hierarchy lookups.
	 *
	 * @since Unreleased Validates SOL organisation hierarchy identifiers.
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
	 * @since Unreleased Reads SOL organisation hierarchy relationships.
	 *
	 * @return array<string, array<int, string>> Parent IDs keyed by child organisation ID.
	 */
	private static function scouting_oidc_roles_authorization_get_organisation_parents(): array {
		global $wpdb;

		$organisation_parents_table = $wpdb->prefix . 'scouting_oidc_sol_organisation_parents';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT child_organisation_id, parent_organisation_id FROM %i', $organisation_parents_table ), ARRAY_A );
		$result = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
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
	 * @since Unreleased Traverses SOL organisation hierarchy relationships.
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
	 * Clears cached active assignments after synchronization changes them.
	 *
	 * @since Unreleased Keeps same-request authorization data current.
	 *
	 * @param int $user_id WordPress user ID.
	 */
	public static function scouting_oidc_roles_authorization_clear_active_assignments( int $user_id ): void {
		unset( self::$active_assignments[ $user_id ] );
	}

	/**
	 * Normalizes one stored role mapping rule.
	 *
	 * @since Unreleased Safely reads stored SOL role mapping rules.
	 *
	 * @param mixed               $stored_rule Stored rule value.
	 * @param array<string, array> $wordpress_roles Available WordPress roles.
	 * @return array{id: string, organisation_id: string, organisation_unit_id: int, role_id: int, wordpress_role: string, login_allowed: bool}|null Normalized rule or null when invalid.
	 */
	private static function scouting_oidc_roles_authorization_normalize_rule( mixed $stored_rule, array $wordpress_roles ): ?array {
		if ( ! is_array( $stored_rule ) ) {
			return null;
		}

		$organisation_id = '';
		if ( isset( $stored_rule['organisation_id'] ) && '' !== $stored_rule['organisation_id'] ) {
			if ( ! is_string( $stored_rule['organisation_id'] ) || 1 !== preg_match( self::ORGANISATION_ID_PATTERN, $stored_rule['organisation_id'] ) ) {
				return null;
			}
			$organisation_id = $stored_rule['organisation_id'];
		}

		$organisation_unit_id = self::scouting_oidc_roles_authorization_normalize_positive_id( $stored_rule['organisation_unit_id'] ?? 0 );
		$role_id              = self::scouting_oidc_roles_authorization_normalize_positive_id( $stored_rule['role_id'] ?? 0 );
		$wordpress_role       = isset( $stored_rule['wordpress_role'] ) && is_string( $stored_rule['wordpress_role'] ) ? sanitize_key( $stored_rule['wordpress_role'] ) : '';
		$id                   = isset( $stored_rule['id'] ) && is_string( $stored_rule['id'] ) ? sanitize_text_field( $stored_rule['id'] ) : '';

		if ( ! isset( $wordpress_roles[ $wordpress_role ] ) || ( '' === $organisation_id && 0 === $organisation_unit_id && 0 === $role_id ) ) {
			return null;
		}

		return array(
			'id'                   => $id,
			'organisation_id'      => $organisation_id,
			'organisation_unit_id' => $organisation_unit_id,
			'role_id'              => $role_id,
			'wordpress_role'       => $wordpress_role,
			'login_allowed'        => ! empty( $stored_rule['login_allowed'] ),
		);
	}

	/**
	 * Checks whether an ordered mapping rule matches any active assignment.
	 *
	 * @since Unreleased Matches role mapping conditions against active assignments.
	 *
	 * @param array{id: string, organisation_id: string, organisation_unit_id: int, role_id: int, wordpress_role: string, login_allowed: bool} $rule Mapping rule.
	 * @param array<int, array{role_id: int, organisation_unit_id: int, organisation_id: string, organisation_ids: array<int, string>}> $assignments Active assignments.
	 * @return bool Whether the rule matches.
	 */
	private static function scouting_oidc_roles_authorization_rule_matches_assignments( array $rule, array $assignments ): bool {
		foreach ( $assignments as $assignment ) {
			if ( '' !== $rule['organisation_id'] && ! in_array( $rule['organisation_id'], $assignment['organisation_ids'], true ) ) {
				continue;
			}
			if ( 0 !== $rule['organisation_unit_id'] && $rule['organisation_unit_id'] !== $assignment['organisation_unit_id'] ) {
				continue;
			}
			if ( 0 !== $rule['role_id'] && $rule['role_id'] !== $assignment['role_id'] ) {
				continue;
			}

			return true;
		}

		return false;
	}

	/**
	 * Normalizes a positive integer identifier or zero.
	 *
	 * @since Unreleased Reads optional role mapping identifiers.
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
