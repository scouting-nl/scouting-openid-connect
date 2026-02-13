<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ErrorHandler {
    /**
     * Build a normalized login error URL.
     *
     * @param string $error_description
     * @param string $hint
     * @param string $message
     * @return string
     */
    public static function login_error_url(string $error_description, string $hint, string $message): string {
        return esc_url_raw(add_query_arg(
            array(
                'login' => 'failed',
                'error_description' => $error_description,
                'hint' => $hint,
                'message' => $message,
            ),
            wp_login_url()
        ));
    }

    /**
     * Redirect to login with a normalized error payload.
     *
     * @param string $error_description
     * @param string $hint
     * @param string $message
     */
    public static function redirect_to_login_error(string $error_description, string $hint, string $message): void {
        wp_safe_redirect(self::login_error_url($error_description, $hint, $message));
        exit;
    }
}
?>
