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
 */
enum LogComponent: string {
	case ASSETS   = 'assets';
	case AUTH     = 'auth';
	case CRONJOB  = 'cronjob';
	case MAIL     = 'mail';
	case OIDC     = 'oidc';
	case SETTINGS = 'settings';
	case USER     = 'user';
}
