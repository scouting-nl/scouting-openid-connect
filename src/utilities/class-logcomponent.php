<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.4.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defines log components for categorizing log entries.
 *
 * Each log entry has a component to support filtering and analysis.
 *
 * @since 2.4.0
 * @since Unreleased Adds the roles component.
 */
enum LogComponent: string {
	case ASSETS   = 'assets';
	case AUTH     = 'auth';
	case CRONJOB  = 'cronjob';
	case MAIL     = 'mail';
	case OIDC     = 'oidc';
	case ROLES    = 'roles';
	case SETTINGS = 'settings';
	case USER     = 'user';
}
