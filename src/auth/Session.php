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
    public function scouting_oidc_session_set(string $key, mixed $value): void {
        set_transient('scouting_oidc_session_'.$this->scouting_oidc_session_get_session_id().'_'.$key, $value, 60*60*1);
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
     * @return string the session ID value or an empty string if the session ID does not exist
     */
    private function scouting_oidc_session_get_session_id(): string {
        // Check if the cookie exists
        if (isset($_COOKIE['scouting_oidc_session'])) {
            // Unslash the cookie value and sanitize it
            return sanitize_text_field(wp_unslash($_COOKIE['scouting_oidc_session']));
        }
        
        // Return empty string if the cookie does not exist
        return '';
    }
}
?>
