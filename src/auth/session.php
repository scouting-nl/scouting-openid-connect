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
        set_transient('scouting_oidc_session_'.$this->scouting_oidc_session_get_cookie().'_'.$key, $value, 60*60*1);
    }

    /**
     * Gets value from the transient session
     * 
     * @param string $key the key to get from the transient session
     * @return mixed the value from the transient session
     */
    public function scouting_oidc_session_get(string $key) {
        $value = get_transient('scouting_oidc_session_'.$this->scouting_oidc_session_get_cookie().'_'.$key);
        return $value;
    }

    /**
     * Delete value from the transient session
     * 
     * @param string $key the key to delete from the transient session
     */
    public function scouting_oidc_session_delete(string $key) {
        delete_transient('scouting_oidc_session_'.$this->scouting_oidc_session_get_cookie().'_'.$key);
    }

    /**
     * Set a user unique session cookie named 'scouting_oidc_session' with a 1 hour expiration time
     */
    public function scouting_oidc_session_set_cookie() {
        $session_id = $this->scouting_oidc_session_get_cookie();
        if ($session_id === null) {
            $session_id = bin2hex(random_bytes(16));
        }
        setcookie('scouting_oidc_session', $session_id, time() + 60*60*1);
    }

    /**
     * Get the scouting_oidc_session cookie value
     * 
     * @return string|null the cookie value or null if the cookie does not exist
     */
    private function scouting_oidc_session_get_cookie() {
        // Check if the cookie exists
        if (isset($_COOKIE['scouting_oidc_session'])) {
            // Unslash the cookie value and sanitize it
            return sanitize_text_field(wp_unslash($_COOKIE['scouting_oidc_session']));
        }
        
        // Return null or a default value if the cookie does not exist
        return null;
    }
}
?>
