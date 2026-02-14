<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Mail {
    /**
     * In-memory cache for SOL user checks during a single request.
     *
     * @var array<string, bool>
     */
    private static array $scouting_oidc_mail_sol_user_cache = [];

    /**
     * Create a plus-addressed email (name+SOL_ID@example.com).
     *
     * Returns the original email when input is invalid.
     *
     * @param string $email Base email address
     * @param string $sol_id SOL member ID
     * @return string Plus-addressed email or original email on invalid input
     */
    public static function scouting_oidc_mail_create_plus_address(string $email, string $sol_id): string {
        if (!is_email($email) || $sol_id === '') {
            return $email;
        }

        [$local_part, $domain] = explode('@', $email, 2);

        return "{$local_part}+{$sol_id}@{$domain}";
    }

    /**
     * Normalize mail recipients so plus-addressed Scouting OIDC aliases
     * (name+SOL_ID@example.com) are sent to the base email address.
     *
     * @param array $args wp_mail arguments
     * @return array Modified wp_mail arguments with normalized recipients
     */
    public static function scouting_oidc_mail_filter_wp_mail(array $args): array {
        // Look for recipients in 'to', 'cc', and 'bcc' fields and normalize them
        foreach (['to', 'cc', 'bcc'] as $field) {
            // Skip if the field is not set or empty
            if (empty($args[$field])) {
                continue;
            }

            // Determine if the field is an array or a comma-separated string and normalize it to an array
            $original_is_array = is_array($args[$field]);

            // Normalize recipients to an array for processing
            $recipients = $original_is_array ? $args[$field] : wp_parse_list((string) $args[$field]);

            // Strip +SOL_ID from each recipient if it belongs to a Scouting OIDC user
            $normalized_recipients = array_map([self::class, 'scouting_oidc_mail_strip_sol_alias_from_recipient'], $recipients);

            // Convert back to original format (array or comma-separated string)
            $args[$field] = $original_is_array ? $normalized_recipients : implode(', ', $normalized_recipients);
        }

        // Return the modified arguments
        return $args;
    }

    /**
     * Strip +SOL_ID from recipient email when SOL_ID belongs to a Scouting OIDC user.
     *
     * @param string $recipient Mail recipient (email or "Name <email>")
     * @return string Recipient with +SOL_ID stripped if it belongs to a Scouting OIDC user, otherwise original recipient
     */
    private static function scouting_oidc_mail_strip_sol_alias_from_recipient(string $recipient): string {
        // Extract the email address from the recipient string (handles "Name <email>" format)
        if (!preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $recipient, $matches)) {
            return $recipient;
        }

        // Validate the extracted email address
        $email = $matches[0];
        if (!is_email($email)) {
            return $recipient;
        }

        // Split the email into local part and domain
        [$local_part, $domain] = explode('@', $email, 2);
        $plus_position = strrpos($local_part, '+');
        if ($plus_position === false) {
            return $recipient;
        }

        // Extract the possible SOL_ID from the local part
        $possible_sol_id = substr($local_part, $plus_position + 1);
        if ($possible_sol_id === '') {
            return $recipient;
        }

        // Check if the SOL_ID belongs to a Scouting OIDC user
        if (!self::scouting_oidc_mail_is_sol_oidc_user($possible_sol_id)) {
            return $recipient;
        }

        // Construct the normalized email by removing the +SOL_ID part
        $normalized_email = substr($local_part, 0, $plus_position) . '@' . $domain;

        // Replace the original email in the recipient string with the normalized email
        return str_replace($email, $normalized_email, $recipient);
    }

    /**
     * Determine whether a SOL ID belongs to a Scouting OIDC user, with request-level cache.
     *
     * @param string $sol_id SOL ID
     * @return bool True when SOL ID exists and is marked as scouting_oidc_user=true
     */
    private static function scouting_oidc_mail_is_sol_oidc_user(string $sol_id): bool {
        if (isset(self::$scouting_oidc_mail_sol_user_cache[$sol_id])) {
            return self::$scouting_oidc_mail_sol_user_cache[$sol_id];
        }

        $user = get_user_by('login', $sol_id);
        $is_sol_oidc_user = $user && get_user_meta($user->ID, 'scouting_oidc_user', true) === 'true';

        self::$scouting_oidc_mail_sol_user_cache[$sol_id] = $is_sol_oidc_user;

        return $is_sol_oidc_user;
    }
}
?>