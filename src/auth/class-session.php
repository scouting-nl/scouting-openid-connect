<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 1.0.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define WordPress constant if not defined.
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

/**
 * Manages the Scouting OpenID Connect transient session.
 *
 * @since 1.0.0
 */
class Session {
	/**
	 * Name of the transient-session cookie.
	 *
	 * @since 2.5.0
	 *
	 * @var string
	 */
	private const COOKIE_NAME = '__Host-scouting_oidc_session';

	/**
	 * Sets value in a transient session for 1 hour.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key the key to set in the transient session.
	 * @param mixed  $value the value to set in the transient session.
	 */
	public function scouting_oidc_session_set( string $key, mixed $value ): void {
		$session_id = $this->scouting_oidc_session_get_session_id();
		if ( '' === $session_id ) {
			return;
		}

		set_transient( $this->transient_key( $session_id, $key ), $value, HOUR_IN_SECONDS );
	}

	/**
	 * Gets value from the transient session.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key the key to get from the transient session.
	 * @return mixed the value from the transient session.
	 */
	public function scouting_oidc_session_get( string $key ): mixed {
		$session_id = $this->scouting_oidc_session_get_session_id();
		if ( '' === $session_id ) {
			return false;
		}

		return get_transient( $this->transient_key( $session_id, $key ) );
	}

	/**
	 * Deletes value from the transient session.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key the key to delete from the transient session.
	 */
	public function scouting_oidc_session_delete( string $key ): void {
		$session_id = $this->scouting_oidc_session_get_session_id();
		if ( '' === $session_id ) {
			return;
		}

		delete_transient( $this->transient_key( $session_id, $key ) );
	}

	/**
	 * Sets a unique session ID in the __Host-scouting_oidc_session cookie for 1 hour.
	 *
	 * @since 2.2.0
	 * @since 2.5.0 Updated the return type.
	 *
	 * @return bool True when a persistent session cookie is available.
	 */
	public function scouting_oidc_session_set_session_id(): bool {
		$session_id = $this->scouting_oidc_session_get_session_id();
		if ( '' !== $session_id ) {
			return true;
		}

		$session_id = $this->generate_session_id();
		return null !== $session_id && $this->scouting_oidc_session_set_cookie( $session_id );
	}

	/**
	 * Rotates the session ID after authentication and discard pre-login state.
	 *
	 * @since 2.5.0
	 *
	 * @return bool True when the session ID was rotated.
	 */
	public function scouting_oidc_session_regenerate_id(): bool {
		$old_session_id = $this->scouting_oidc_session_get_session_id();
		if ( '' === $old_session_id ) {
			return false;
		}

		$preserved = array(
			'scouting_oidc_id_token'            => $this->scouting_oidc_session_get( 'scouting_oidc_id_token' ),
			'scouting_oidc_post_login_redirect' => $this->scouting_oidc_session_get( 'scouting_oidc_post_login_redirect' ),
		);

		$session_id = $this->generate_session_id();
		if ( null === $session_id || ! $this->scouting_oidc_session_set_cookie( $session_id ) ) {
			return false;
		}

		foreach ( array( 'scouting_oidc_redirect_uri', 'scouting_oidc_states', 'scouting_oidc_nonces', 'scouting_oidc_code_verifiers', 'scouting_oidc_post_login_redirect', 'scouting_oidc_redirects', 'scouting_oidc_id_token' ) as $key ) {
			delete_transient( $this->transient_key( $old_session_id, $key ) );
		}

		foreach ( $preserved as $key => $value ) {
			if ( false !== $value ) {
				$this->scouting_oidc_session_set( $key, $value );
			}
		}

		return true;
	}

	/**
	 * Gets the scouting_oidc_session session ID value.
	 *
	 * @since 2.2.0
	 *
	 * @return string the session ID value or an empty string if the session ID does not exist.
	 */
	private function scouting_oidc_session_get_session_id(): string {
		if ( ! is_ssl() ) {
			return '';
		}

		$session_id = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';
		if ( is_string( $session_id ) && preg_match( '/\A[a-f0-9]{32}\z/', $session_id ) === 1 ) {
			return $session_id;
		}

		return '';
	}

	/**
	 * Sets a host-only secure session cookie and expose it to the current request.
	 *
	 * @since 1.0.0
	 * @since 2.5.0 Added the `$session_id` parameter. Updated the return type.
	 *
	 * @param string $session_id Session identifier.
	 * @return bool True when the cookie was successfully queued.
	 */
	private function scouting_oidc_session_set_cookie( string $session_id ): bool {
		if ( ! is_ssl() || headers_sent() ) {
			return false;
		}

		$was_set = setcookie(
			self::COOKIE_NAME,
			$session_id,
			array(
				'expires'  => time() + HOUR_IN_SECONDS,
				'path'     => '/',
				'secure'   => true,
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		if ( ! $was_set ) {
			return false;
		}

		$_COOKIE[ self::COOKIE_NAME ] = $session_id;
		return true;
	}

	/**
	 * Generates a cryptographically secure session identifier.
	 *
	 * @since 2.5.0
	 *
	 * @return string|null Session identifier, or null when secure randomness is unavailable.
	 */
	private function generate_session_id(): ?string {
		try {
			return bin2hex( random_bytes( 16 ) );
		} catch ( \Exception $exception ) {
			return null;
		}
	}

	/**
	 * Builds the transient key for a validated session identifier.
	 *
	 * @since 2.5.0
	 *
	 * @param string $session_id Session identifier.
	 * @param string $key Session data key.
	 * @return string Transient key.
	 */
	private function transient_key( string $session_id, string $key ): string {
		return 'scouting_oidc_session_' . $session_id . '_' . $key;
	}
}
