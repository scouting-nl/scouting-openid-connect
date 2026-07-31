<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Define WordPress constant if not defined.
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

class Session {
    private const COOKIE_NAME = '__Host-scouting_oidc_session';

    /**
     * Sets value in a transient session for 1 hour
     * 
     * @param string $key the key to set in the transient session
     * @param mixed $value the value to set in the transient session
     */
    public function scouting_oidc_session_set(string $key, mixed $value): void {
        set_transient('scouting_oidc_session_'.$this->scouting_oidc_session_get_session_id().'_'.$key, $value, HOUR_IN_SECONDS);
    }

    /**
     * Gets value from the transient session
     * 
     * @param string $key the key to get from the transient session
     * @return mixed the value from the transient session
     */
    public function scouting_oidc_session_get(string $key): mixed {
        $value = get_transient('scouting_oidc_session_'.$this->scouting_oidc_session_get_session_id().'_'.$key);
        return $value;
    }

    /**
     * Delete value from the transient session
     * 
     * @param string $key the key to delete from the transient session
     */
    public function scouting_oidc_session_delete(string $key): void {
        delete_transient('scouting_oidc_session_'.$this->scouting_oidc_session_get_session_id().'_'.$key);
    }

    /**
     * Set a user unique session ID named 'scouting_oidc_session' with a 1 hour expiration time
     */
    public function scouting_oidc_session_set_session_id(): void {
        $session_id = $this->scouting_oidc_session_get_session_id();
        if (empty($session_id)) {
            $this->scouting_oidc_session_set_cookie(bin2hex(random_bytes(16)));
        }
    }

    /**
     * Rotate the session ID after authentication and discard pre-login state.
     */
    public function scouting_oidc_session_regenerate_id(): void {
        $preserved = array(
            'scouting_oidc_id_token' => $this->scouting_oidc_session_get('scouting_oidc_id_token'),
            'scouting_oidc_post_login_redirect' => $this->scouting_oidc_session_get('scouting_oidc_post_login_redirect'),
        );

        foreach (array('scouting_oidc_redirect_uri', 'scouting_oidc_states', 'scouting_oidc_nonces', 'scouting_oidc_code_verifiers', 'scouting_oidc_post_login_redirect', 'scouting_oidc_redirects', 'scouting_oidc_id_token') as $key) {
            $this->scouting_oidc_session_delete($key);
        }

        $this->scouting_oidc_session_set_cookie(bin2hex(random_bytes(16)));

        foreach ($preserved as $key => $value) {
            if ($value !== false) {
                $this->scouting_oidc_session_set($key, $value);
            }
        }
    }

    /**
     * Get the scouting_oidc_session session ID value
     * 
     * @return string the session ID value or an empty string if the session ID does not exist
     */
    private function scouting_oidc_session_get_session_id(): string {
        $session_id = isset($_COOKIE[self::COOKIE_NAME]) ? wp_unslash($_COOKIE[self::COOKIE_NAME]) : '';
        if (is_string($session_id) && preg_match('/\A[a-f0-9]{32}\z/', $session_id) === 1) {
            return $session_id;
        }

        return '';
    }

    /**
     * Set a host-only secure session cookie and expose it to the current request.
     *
     * @param string $session_id Session identifier
     */
    private function scouting_oidc_session_set_cookie(string $session_id): void {
        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, $session_id, [
                'expires' => time() + HOUR_IN_SECONDS,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        $_COOKIE[self::COOKIE_NAME] = $session_id;
    }
}
?>
