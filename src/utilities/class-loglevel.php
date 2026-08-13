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
 * Defines log levels for the logging system.
 *
 * This enum lists the PSR-3 severity levels stored in the database.
 *
 * @since 2.4.0
 */
enum LogLevel: string {
	case EMERGENCY = 'emergency';
	case ALERT     = 'alert';
	case CRITICAL  = 'critical';
	case ERROR     = 'error';
	case WARNING   = 'warning';
	case NOTICE    = 'notice';
	case INFO      = 'info';
	case DEBUG     = 'debug';
}
