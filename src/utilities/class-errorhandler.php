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
	 * Explains scope rejections without exposing arbitrary provider error details.
	 *
	 * @since Unreleased Adds actionable guidance for all rejected OIDC scopes.
	 *
	 * @param string $hint Provider hint or description.
	 * @param string $error Error code returned by the provider.
	 * @return string User-facing hint, unchanged for unrelated errors.
	 */
	public static function scope_error_hint( string $hint, string $error ): string {
		$scope_match = array();
		$has_scope   = 1 === preg_match( '/^Check the ([a-zA-Z0-9_.:-]{1,80}) scope\.?$/i', trim( $hint ), $scope_match );
		if ( 'invalid_scope' !== $error && ! $has_scope ) {
			return $hint;
		}

		$message = $has_scope
			? sprintf(
				/* translators: %s: OpenID Connect scope rejected by Scouts Online, for example roles. */
				__( 'Scouts Online rejected the requested "%s" scope. Sign-in could not be completed. Please contact the website administrator.', 'scouting-openid-connect' ),
				$scope_match[1]
			)
			: __( 'Scouts Online rejected one or more requested scopes. Sign-in could not be completed. Please contact the website administrator.', 'scouting-openid-connect' );

		return $message . ' ' . __( 'For administrators: compare the plugin’s OpenID Connect Scopes setting with the scopes enabled for this website under Managed websites at login.scouting.nl. Enable the requested scopes there, or remove optional scopes you do not use from WordPress. Keep openid, membership, profile, and email enabled in both places. Then try signing in again.', 'scouting-openid-connect' );
	}

	/**
	 * Generates a login error URL with the given error details as query parameters.
	 *
	 * @since 2.3.0
	 * @since Unreleased Replaces terse scope errors with user and administrator guidance.
	 *
	 * @param string      $error_description A description of the error that occurred.
	 * @param string      $hint A hint to help the user understand the error or how to resolve it.
	 * @param string      $message A user-friendly message to display on the login page.
	 * @param string|null $error Optional. An error code or identifier for the error. Default null.
	 * @return string The generated login error URL with the error details as query parameters.
	 */
	public static function login_error_url( string $error_description, string $hint, string $message, ?string $error = null ): string {
		$scope_error = in_array( 'invalid_scope', array( $error_description, $message, $error ), true ) ? 'invalid_scope' : '';
		$query_args  = array(
			'login'             => 'failed',
			'error_description' => $error_description,
			'hint'              => self::scope_error_hint( $hint, $scope_error ),
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
	 * @since Unreleased Uses the same scope guidance when headers prevent redirecting.
	 *
	 * @param string      $error_description A description of the error that occurred.
	 * @param string      $hint A hint to help the user understand the error or how to resolve it.
	 * @param string      $message A user-friendly message to display on the login page.
	 * @param string|null $error Optional. An error code or identifier for the error. Default null.
	 */
	public static function redirect_to_login_error( string $error_description, string $hint, string $message, ?string $error = null ): void {
		if ( headers_sent() ) {
			$scope_error = in_array( 'invalid_scope', array( $error_description, $message, $error ), true ) ? 'invalid_scope' : '';
			wp_die( esc_html( self::scope_error_hint( $hint, $scope_error ) ) );
			exit;
		}

		wp_safe_redirect( self::login_error_url( $error_description, $hint, $message, $error ) );
		exit;
	}
}
