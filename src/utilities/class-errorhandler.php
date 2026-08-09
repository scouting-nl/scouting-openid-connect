<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 2.3.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds login error redirects.
 *
 * @since 2.3.0
 */
class ErrorHandler {
	/**
	 * Generates a login error URL with the given error details as query parameters.
	 *
	 * @since 2.3.0
	 *
	 * @param string      $error_description A description of the error that occurred.
	 * @param string      $hint A hint to help the user understand the error or how to resolve it.
	 * @param string      $message A user-friendly message to display on the login page.
	 * @param string|null $error Optional. An error code or identifier for the error. Default null.
	 * @return string The generated login error URL with the error details as query parameters.
	 */
	public static function login_error_url( string $error_description, string $hint, string $message, ?string $error = null ): string {
		$query_args = array(
			'login'             => 'failed',
			'error_description' => $error_description,
			'hint'              => $hint,
			'message'           => $message,
		);

		if ( ! empty( $error ) ) {
			$query_args['error'] = $error;
		}

		return esc_url_raw( add_query_arg( $query_args, wp_login_url() ) );
	}

	/**
	 * Redirects to login with a normalized error payload.
	 *
	 * @since 2.3.0
	 *
	 * @param string      $error_description A description of the error that occurred.
	 * @param string      $hint A hint to help the user understand the error or how to resolve it.
	 * @param string      $message A user-friendly message to display on the login page.
	 * @param string|null $error Optional. An error code or identifier for the error. Default null.
	 */
	public static function redirect_to_login_error( string $error_description, string $hint, string $message, ?string $error = null ): void {
		if ( headers_sent() ) {
			wp_die( esc_html( $hint ) );
			exit;
		}

		wp_safe_redirect( self::login_error_url( $error_description, $hint, $message, $error ) );
		exit;
	}
}
