<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since Unreleased Adds Groups membership synchronization.
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Synchronizes SOL-managed memberships with the optional Groups plugin.
 *
 * @since Unreleased Adds Groups membership synchronization.
 */
class GroupsIntegration {
	/**
	 * User meta key tracking Groups memberships created by this plugin.
	 *
	 * @since Unreleased Preserves independently managed Groups memberships.
	 */
	private const MANAGED_GROUP_IDS_META_KEY = 'scouting_oidc_managed_group_ids';

	/**
	 * Checks whether the Groups membership API is available.
	 *
	 * @since Unreleased Detects the optional Groups plugin.
	 *
	 * @return bool Whether Groups membership operations are available.
	 */
	public static function scouting_oidc_groups_integration_is_available(): bool {
		return class_exists( 'Groups_Group' ) && class_exists( 'Groups_User_Group' );
	}

	/**
	 * Gets Groups memberships available for SOL role mapping.
	 *
	 * @since Unreleased Lists Groups memberships for SOL role mapping.
	 *
	 * @return array<int, array{group_id: int, name: string}> Groups memberships.
	 */
	public static function scouting_oidc_groups_integration_get_groups(): array {
		if ( ! self::scouting_oidc_groups_integration_is_available() ) {
			return array();
		}

		$groups = \Groups_Group::get_groups(
			array(
				'fields'   => 'group_id,name',
				'order_by' => 'name',
				'order'    => 'ASC',
			)
		);
		$result = array();
		foreach ( is_array( $groups ) ? $groups : array() as $group ) {
			if ( isset( $group->group_id, $group->name ) && is_numeric( $group->group_id ) && is_string( $group->name ) ) {
				$result[] = array(
					'group_id' => (int) $group->group_id,
					'name'     => $group->name,
				);
			}
		}

		return $result;
	}

	/**
	 * Validates Groups memberships selected in a mapping rule.
	 *
	 * @since Unreleased Validates Groups membership selections.
	 *
	 * @param array<int, int> $group_ids Groups membership IDs.
	 * @return bool Whether all selected memberships exist.
	 */
	public static function scouting_oidc_groups_integration_group_ids_exist( array $group_ids ): bool {
		if ( ! self::scouting_oidc_groups_integration_is_available() ) {
			return empty( $group_ids );
		}

		foreach ( $group_ids as $group_id ) {
			if ( ! \Groups_Group::exists( $group_id ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Synchronizes only Groups memberships created by this plugin for a user.
	 *
	 * Existing memberships created outside this plugin are not claimed or removed.
	 *
	 * @since Unreleased Synchronizes SOL role mapping Groups memberships.
	 *
	 * @param int             $user_id WordPress user ID.
	 * @param array<int, int> $desired_group_ids Desired Groups membership IDs.
	 * @return bool Whether all requested changes succeeded.
	 */
	public static function scouting_oidc_groups_integration_sync_user_groups( int $user_id, array $desired_group_ids ): bool {
		if ( ! self::scouting_oidc_groups_integration_is_available() ) {
			return true;
		}

		if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
			return false;
		}

		$desired_group_ids = self::scouting_oidc_groups_integration_normalize_group_ids( $desired_group_ids );
		$managed_group_ids = self::scouting_oidc_groups_integration_normalize_group_ids( get_user_meta( $user_id, self::MANAGED_GROUP_IDS_META_KEY, true ) );
		$removed_groups    = self::scouting_oidc_groups_integration_remove_unwanted_groups( $user_id, $managed_group_ids, $desired_group_ids );
		$desired_groups    = self::scouting_oidc_groups_integration_add_desired_groups( $user_id, $managed_group_ids, $desired_group_ids );
		$remaining_ids     = array_merge( $removed_groups['group_ids'], $desired_groups['group_ids'] );
		$success           = $removed_groups['success'] && $desired_groups['success'];

		$remaining_ids = array_values( array_unique( $remaining_ids ) );
		if ( empty( $remaining_ids ) ) {
			delete_user_meta( $user_id, self::MANAGED_GROUP_IDS_META_KEY );
		} else {
			update_user_meta( $user_id, self::MANAGED_GROUP_IDS_META_KEY, $remaining_ids );
		}

		return $success;
	}

	/**
	 * Removes managed memberships that are no longer selected by the mapping.
	 *
	 * @since Unreleased Removes obsolete SOL-managed Groups memberships.
	 *
	 * @param int             $user_id WordPress user ID.
	 * @param array<int, int> $managed_group_ids Groups membership IDs managed by this plugin.
	 * @param array<int, int> $desired_group_ids Groups membership IDs selected by the mapping.
	 * @return array{group_ids: array<int, int>, success: bool} Failed removals and reconciliation status.
	 */
	private static function scouting_oidc_groups_integration_remove_unwanted_groups( int $user_id, array $managed_group_ids, array $desired_group_ids ): array {
		$remaining_ids = array();
		$success       = true;
		foreach ( $managed_group_ids as $group_id ) {
			if ( in_array( $group_id, $desired_group_ids, true ) ) {
				continue;
			}

			$membership = \Groups_User_Group::read( $user_id, $group_id );
			if ( false !== $membership && ! \Groups_User_Group::delete( $user_id, $group_id ) ) {
				$remaining_ids[] = $group_id;
				$success         = false;
			}
		}

		return array(
			'group_ids' => $remaining_ids,
			'success'   => $success,
		);
	}

	/**
	 * Adds selected Groups memberships that are not already direct memberships.
	 *
	 * @since Unreleased Adds selected SOL-managed Groups memberships.
	 *
	 * @param int             $user_id WordPress user ID.
	 * @param array<int, int> $managed_group_ids Groups membership IDs managed by this plugin.
	 * @param array<int, int> $desired_group_ids Groups membership IDs selected by the mapping.
	 * @return array{group_ids: array<int, int>, success: bool} Managed memberships and reconciliation status.
	 */
	private static function scouting_oidc_groups_integration_add_desired_groups( int $user_id, array $managed_group_ids, array $desired_group_ids ): array {
		$managed_ids = array();
		$success     = true;
		foreach ( $desired_group_ids as $group_id ) {
			if ( ! \Groups_Group::exists( $group_id ) ) {
				$success = false;
				continue;
			}

			$membership = \Groups_User_Group::read( $user_id, $group_id );
			if ( false === $membership ) {
				if ( \Groups_User_Group::create( array( 'user_id' => $user_id, 'group_id' => $group_id ) ) ) {
					$managed_ids[] = $group_id;
				} else {
					$success = false;
				}
			} elseif ( in_array( $group_id, $managed_group_ids, true ) ) {
				$managed_ids[] = $group_id;
			}
		}

		return array(
			'group_ids' => $managed_ids,
			'success'   => $success,
		);
	}

	/**
	 * Normalizes positive Groups membership IDs.
	 *
	 * @since Unreleased Normalizes Groups membership IDs.
	 *
	 * @param mixed $value Raw Groups membership IDs.
	 * @return array<int, int> Normalized Groups membership IDs.
	 */
	public static function scouting_oidc_groups_integration_normalize_group_ids( mixed $value ): array {
		$values = is_array( $value ) ? $value : array( $value );
		$result = array();
		foreach ( $values as $group_id ) {
			if ( is_int( $group_id ) && $group_id > 0 ) {
				$result[] = $group_id;
				continue;
			}

			if ( ! is_string( $group_id ) || 1 !== preg_match( '/^[1-9]\d*$/', $group_id ) ) {
				continue;
			}

			$normalized_group_id = filter_var( $group_id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
			if ( false !== $normalized_group_id ) {
				$result[] = (int) $normalized_group_id;
			}
		}

		return array_values( array_unique( $result ) );
	}
}
