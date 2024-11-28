<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Session {
    // Start the session if it is not already started
    private static function scouting_oidc_session_start() {
        if (session_status() === PHP_SESSION_NONE && headers_sent() === false) {
            session_start();
        }
    }

    // Write and close the session
    private static function scouting_oidc_session_write_close() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    // Destroy the session if it is active
    public static function scouting_oidc_session_end() {
        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
        }
    }

    /**
     * Sets value in session with key
     * 
     * @param string $key the key to set in the session
     * @param mixed $value the value to set in the session
     */
    public function scouting_oidc_session_set(string $key, mixed $value) {
        $this->scouting_oidc_session_start(); // Start session if not already started
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION[$key] = $value;
        }
        $this->scouting_oidc_session_write_close(); // Write and close session
    }

    /**
     * Gets value from session with key
     * 
     * @param string $key the key to get from the session
     * @return string|array|false the sanitized session value or false if the key does not exist
     */
    public function scouting_oidc_session_get(string $key) {
        $this->scouting_oidc_session_start(); // Start session if not already started
        $value = false;
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Ensure $_SESSION is initialized as an array
            if (!is_array($_SESSION)) {
                $_SESSION = [];
            }
            
            // Check if the key exists in the session
            if (array_key_exists($key, $_SESSION)) {
                // Check if the value is an array and sanitize accordingly
                if (is_array($_SESSION[$key])) {
                    $value = array_map('sanitize_text_field', $_SESSION[$key]);
                } else {
                    $value = sanitize_text_field($_SESSION[$key]);
                }
            }
        }
        $this->scouting_oidc_session_write_close(); // Write and close session
        return $value;
    }

    /**
     * Delete value from session with key
     * 
     * @param string $key the key to unset in the session
     */
    public function scouting_oidc_session_delete(string $key) {
        $this->scouting_oidc_session_start(); // Start session if not already started
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
        $this->scouting_oidc_session_write_close(); // Write and close session
    }
}
?>
