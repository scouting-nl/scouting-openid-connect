<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class ErrorHandler {
    /**
     * Generate a login error URL with the given error details as query parameters.
     *
     * @param string $error_description A description of the error that occurred.
     * @param string $hint A hint to help the user understand the error or how to resolve it.
     * @param string $message A user-friendly message to display on the login page.
     * @param string|null $error An optional error code or identifier for the error.
     * @return string 
     */
    public static function login_error_url(string $error_description, string $hint, string $message, ?string $error = null): string {
        $query_args = array(
            'login' => 'failed',
            'error_description' => $error_description,
            'hint' => $hint,
            'message' => $message,
        );

        if (!empty($error)) {
            $query_args['error'] = $error;
        }

        return esc_url_raw(add_query_arg($query_args, wp_login_url()));
    }

    /**
     * Redirect to login with a normalized error payload.
     *
     * @param string $error_description A description of the error that occurred.
     * @param string $hint A hint to help the user understand the error or how to resolve it.
     * @param string $message A user-friendly message to display on the login page.
     * @param string|null $error An optional error code or identifier for the error.
     * @return void
     */
    public static function redirect_to_login_error(string $error_description, string $hint, string $message, ?string $error = null): void {
        wp_safe_redirect(self::login_error_url($error_description, $hint, $message, $error));
        exit;
    }
}
?>
