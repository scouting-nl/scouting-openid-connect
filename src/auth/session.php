<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Session {
    /**
     * Sets value in a transient session for 1 hour
     * 
     * @param string $key the key to set in the transient session
     * @param mixed $value the value to set in the transient session
     */
    public function scouting_oidc_session_set(string $key, mixed $value) {
        set_transient('scouting_oidc_session_'.$this->scouting_oidc_session_get_session_id().'_'.$key, $value, 60*60*1);
    }

    /**
     * Gets value from the transient session
     * 
     * @param string $key the key to get from the transient session
     * @return mixed the value from the transient session
     */
    public function scouting_oidc_session_get(string $key) {
        $value = get_transient('scouting_oidc_session_'.$this->scouting_oidc_session_get_session_id().'_'.$key);
        return $value;
    }

    /**
     * Delete value from the transient session
     * 
     * @param string $key the key to delete from the transient session
     */
    public function scouting_oidc_session_delete(string $key) {
        delete_transient('scouting_oidc_session_'.$this->scouting_oidc_session_get_session_id().'_'.$key);
    }

    /**
     * Set a user unique session ID named 'scouting_oidc_session' with a 1 hour expiration time
     */
    public function scouting_oidc_session_set_session_id() {
        $session_id = $this->scouting_oidc_session_get_session_id();
        if ($session_id === null) {
            $session_id = bin2hex(random_bytes(16));

            // Get the domain for the cookie
            $domain = wp_parse_url(home_url(), PHP_URL_HOST);
            if (empty( $domain )) {
                $domain = '';
            }

            setcookie('scouting_oidc_session', $session_id, [
                'expires' => time() + 3600,
                'path' => '/',
                'domain' => $domain,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            // Make the new session ID visible to this request so transients use the same session id
            $_COOKIE['scouting_oidc_session'] = $session_id;
        }
    }

    /**
     * Get the scouting_oidc_session session ID value
     * 
     * @return string|null the session ID value or null if the session ID does not exist
     */
    private function scouting_oidc_session_get_session_id() {
        // Check if the cookie exists
        if (isset($_COOKIE['scouting_oidc_session'])) {
            // Unslash the cookie value and sanitize it
            return sanitize_text_field(wp_unslash($_COOKIE['scouting_oidc_session']));
        }
        
        // Return null or a default value if the session ID does not exist
        return null;
    }
}
?>
