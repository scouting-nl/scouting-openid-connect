<?php
namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

require_once plugin_dir_path(__FILE__) . 'Logger.php';

use ScoutingOIDC\Logger;

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

        $plus_address = "{$local_part}+{$sol_id}@{$domain}";
        Logger::debug(LogType::MAIL, "Created plus-addressed email: {$plus_address} from base email: {$email}", null, $sol_id);

        return $plus_address;
    }

    /**
     * Normalize mail recipients so plus-addressed Scouting OIDC aliases
     * (name+SOL_ID@example.com) are sent to the base email address.
     *
     * @param array $args wp_mail arguments
     * @return array Modified wp_mail arguments with normalized recipients
     */
    public static function scouting_oidc_mail_filter_wp_mail(array $args): array {
        // Define the recipient fields to normalize
        $supported_fields = ['to', 'cc', 'bcc'];

        Logger::debug(LogType::MAIL, 'scouting_oidc_mail_filter_wp_mail invoked; keys: ' . implode(', ', array_keys($args)));

        // Check for plus-addressing markers in recipients and headers
        [$recipient_has_plus, $headers_has_plus] = self::scouting_oidc_mail_value_contains_plus($args, $supported_fields);

        // Skip normalization work when no plus-addressing markers are present
        if (!$recipient_has_plus && !$headers_has_plus) {
            Logger::debug(LogType::MAIL, 'Skipping normalization as no plus-addressing markers were detected in recipients or headers');
            return $args;
        }

        // Normalize recipient fields (to/cc/bcc) in wp_mail arguments if needed
        if ($recipient_has_plus) {
            Logger::debug(LogType::MAIL, 'Plus-addressing detected in recipient fields; normalizing recipients');
            $args = self::scouting_oidc_mail_normalize_recipient_fields($args, $supported_fields);
        }

        // Normalize email addresses found in headers as well if needed
        if ($headers_has_plus && !empty($args['headers'])) {
            Logger::debug(LogType::MAIL, 'Plus-addressing detected in headers; normalizing headers');
            $args['headers'] = self::scouting_oidc_mail_normalize_headers($args['headers']);
        }

        // Return the modified arguments
        return $args;
    }

    /**
     * Determine whether any relevant wp_mail field contains a plus sign used by plus-addressing.
     *
     * @param array $args wp_mail arguments
     * @param array $supported_fields Recipient fields to inspect
     * @return array{0: bool, 1: bool} [recipient_has_plus, headers_has_plus]
     *         recipient_has_plus: True if a '+' appears in any recipient field (to, cc, bcc)
     *         headers_has_plus: True if a '+' appears in any header value
     */
    private static function scouting_oidc_mail_value_contains_plus(array $args, array $supported_fields): array {
        $recipient_has_plus = false;
        $headers_has_plus = false;

        // Check recipient fields for plus signs
        foreach ($supported_fields as $field) {
            if (empty($args[$field])) {
                continue;
            }

            $value = $args[$field];
            if (is_string($value) && str_contains($value, '+')) {
                Logger::debug(LogType::MAIL, "Plus sign detected in recipient field '{$field}' with value: '{$value}'");
                $recipient_has_plus = true;
                break;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (is_string($item) && str_contains($item, '+')) {
                        Logger::debug(LogType::MAIL, "Plus sign detected in recipient field '{$field}' with array item value: '{$item}'");
                        $recipient_has_plus = true;
                        break 2;
                    }
                }
            }
        }

        // Check headers for plus signs as well, since they can contain recipient-like values
        if (!empty($args['headers'])) {
            $headers = $args['headers'];
            if (is_string($headers) && str_contains($headers, '+')) {
                Logger::debug(LogType::MAIL, "Plus sign detected inside headers with value: '{$headers}'"); 
                $headers_has_plus = true;
            } elseif (is_array($headers)) {
                foreach ($headers as $header_item) {
                    if (is_string($header_item) && str_contains($header_item, '+')) {
                        Logger::debug(LogType::MAIL, "Plus sign detected in headers with array item value: '{$header_item}'");
                        $headers_has_plus = true;
                        break;
                    }
                }
            }
        }

        return [$recipient_has_plus, $headers_has_plus];
    }

    /**
     * Normalize recipient fields (to/cc/bcc) in wp_mail arguments.
     *
     * @param array $args wp_mail arguments
     * @param array $supported_fields Recipient fields to inspect and normalize
     * @return array Modified wp_mail arguments
     */
    private static function scouting_oidc_mail_normalize_recipient_fields(array $args, array $supported_fields): array {
        // Normalize each supported recipient field (to/cc/bcc) if it exists in the arguments
        foreach ($supported_fields as $field) {
            // Skip if the field is not set or empty
            if (empty($args[$field])) {
                continue;
            }

            // Determine if the field is an array or a comma-separated string and normalize it to an array
            $original_is_array = is_array($args[$field]);

            // Normalize recipients to an array for processing
            $recipients = $original_is_array ? $args[$field] : wp_parse_list((string) $args[$field]);

            Logger::debug(LogType::MAIL, "Normalizing " . count($recipients) . " recipients in field '{$field}'");

            // Strip +SOL_ID from each recipient if it belongs to a Scouting OIDC user
            $normalized_recipients = array_map([self::class, 'scouting_oidc_mail_strip_sol_alias_from_recipient'], $recipients);

            Logger::debug(LogType::MAIL, "Normalized " . count($normalized_recipients) . " recipients in field '{$field}'");

            // Convert back to original format (array or comma-separated string)
            $args[$field] = $original_is_array ? $normalized_recipients : implode(', ', $normalized_recipients);
        }

        // Return the modified arguments
        return $args;
    }

    /**
     * Normalize recipient-like headers in wp_mail arguments.
     *
     * Supports string and array header formats.
     *
     * @param string|array $headers wp_mail headers
     * @return string|array Normalized headers
     */
    private static function scouting_oidc_mail_normalize_headers(string|array $headers): string|array {
        // Define supported recipient-like headers for normalization
        $supported_headers = ['to', 'cc', 'bcc', 'from', 'reply-to'];

        // If headers are in array format, normalize only supported recipient-like headers and return the modified array
        if (is_array($headers)) {
            Logger::debug(LogType::MAIL, 'Normalizing headers keys (' . implode(', ', array_keys($headers)) . ') in array format');
            foreach ($headers as $key => $value) {
                // Check if the header key is a supported recipient-like header (case-insensitive)
                if (is_string($key) && in_array(strtolower($key), $supported_headers, true)) {
                    $headers[$key] = self::scouting_oidc_mail_normalize_header_value((string) $value);
                    continue;
                }

                // Other headers are left untouched to avoid unintended normalization of non-recipient values.
                if (is_string($value)) {
                    $headers[$key] = self::scouting_oidc_mail_normalize_header_line($value, $supported_headers);
                }
            }

            return $headers;
        }

        // If headers are in string format, split into lines and normalize each line
        Logger::debug(LogType::MAIL, 'Normalizing headers in string format');
        $header_lines = preg_split('/\r\n|\r|\n/', $headers);
        if (!is_array($header_lines)) {
            Logger::debug(LogType::MAIL, 'Failed to split headers into lines');
            return $headers;
        }

        Logger::debug(LogType::MAIL, 'Normalizing ' . count($header_lines) . ' header lines in string format');

        // Normalize each header line and reconstruct the headers string
        $normalized_lines = array_map(
            static fn(string $line): string => self::scouting_oidc_mail_normalize_header_line($line, $supported_headers),
            $header_lines
        );

        // Reconstruct the headers string with normalized lines
        Logger::debug(LogType::MAIL, 'Normalized ' . count($normalized_lines) . ' header lines in string format');
        return implode("\r\n", $normalized_lines);
    }

    /**
     * Normalize a single header line if it is a supported recipient-like header.
     *
     * @param string $line Header line in the format "Header-Name: value"
     * @param array $supported_headers Supported lowercase header names
     * @return string Normalized or original header line
     */
    private static function scouting_oidc_mail_normalize_header_line(string $line, array $supported_headers): string {
        // Skip lines that are empty, contain only whitespace, or do not match the "Header-Name: value" format
        if ($line === '' || preg_match('/^\s+/', $line)) {
            return $line;
        }

        // Parse the header line into name and value
        if (!preg_match('/^([A-Za-z0-9-]+)\s*:(.*)$/', $line, $matches)) {
            Logger::debug(LogType::MAIL, "Header line did not match name-value format; skipping normalization for line: '{$line}'");
            return $line;
        }

        // Check if the header name is in the list of supported headers for normalization
        $name = $matches[1];
        $value = $matches[2];
        $normalized_name = strtolower($name);
        if (!in_array($normalized_name, $supported_headers, true)) {
            Logger::debug(LogType::MAIL, "Header '{$name}' is not a supported recipient-like header; skipping normalization for line: '{$line}'");
            return $line;
        }

        // Normalize the header value and reconstruct the header line
        $normalized_value = self::scouting_oidc_mail_normalize_header_value($value);
        Logger::debug(LogType::MAIL, "Header line with name '{$name}' normalized from value '{$value}' to '{$normalized_value}'");
        return $name . ': ' . $normalized_value;
    }

    /**
     * Normalize one header value containing one or more recipients.
     *
     * @param string $value Header value
     * @return string Normalized header value
     */
    private static function scouting_oidc_mail_normalize_header_value(string $value): string {
        // Parse the header value as a list of recipients and normalize each one
        $recipients = wp_parse_list($value);

        Logger::debug(LogType::MAIL, "Normalizing " . count($recipients) . " recipients in header value: '{$value}'");

        // Strip +SOL_ID from each recipient if it belongs to a Scouting OIDC user
        $normalized_recipients = array_map([self::class, 'scouting_oidc_mail_strip_sol_alias_from_recipient'], $recipients);

        Logger::debug(LogType::MAIL, "Normalized " . count($normalized_recipients) . " recipients in new header value: '" . implode(', ', $normalized_recipients) . "'");

        // Return the normalized recipients as a comma-separated string
        return implode(', ', $normalized_recipients);
    }

    /**
     * Strip +SOL_ID from recipient email when SOL_ID belongs to a Scouting OIDC user.
     *
     * The parser uses the rightmost '+' in the local-part as delimiter for SOL_ID.
     * Example: user+group+123456@example.com => SOL_ID is interpreted as 123456,
     * and the normalized address becomes user+group@example.com when valid.
     *
     * @param string $recipient Mail recipient (email or "Name <email>")
     * @return string Recipient with +SOL_ID stripped if it belongs to a Scouting OIDC user, otherwise original recipient
     */
    private static function scouting_oidc_mail_strip_sol_alias_from_recipient(string $recipient): string {
        // Extract and validate email address from recipient string
        $email = self::scouting_oidc_mail_extract_valid_email_from_recipient($recipient);
        if ($email === null) {
            Logger::debug(LogType::MAIL, "Recipient '{$recipient}' does not contain a valid email address; skipping normalization");
            return $recipient;
        }

        // Split the email into local part and domain
        [$local_part, $domain] = explode('@', $email, 2);
        $plus_position = strrpos($local_part, '+');
        if ($plus_position === false) {
            Logger::debug(LogType::MAIL, "Email '{$email}' does not contain a plus sign in the local part; skipping normalization");
            return $recipient;
        }

        // Extract the possible SOL_ID from the local part (after the rightmost '+')
        $possible_sol_id = substr($local_part, $plus_position + 1);
        if ($possible_sol_id === '') {
            Logger::debug(LogType::MAIL, "Email '{$email}' has a plus sign but no SOL ID after it; skipping normalization");
            return $recipient;
        }

        // Validate SOL_ID format (e.g., ensure it is numeric) before performing user lookup
        if (!ctype_digit($possible_sol_id)) {
            Logger::debug(LogType::MAIL, "Email '{$email}' has a plus sign but the SOL ID '{$possible_sol_id}' is not numeric; skipping normalization");
            return $recipient;
        }
        // Check if the SOL_ID belongs to a Scouting OIDC user
        Logger::debug(LogType::MAIL, "Email '{$email}' has a plus sign with possible SOL ID '{$possible_sol_id}'; checking if it belongs to a Scouting OIDC user");
        if (!self::scouting_oidc_mail_is_sol_oidc_user($possible_sol_id)) {
            Logger::debug(LogType::MAIL, "Email '{$email}' has a plus sign with possible SOL ID '{$possible_sol_id}', but it does not belong to a Scouting OIDC user; skipping normalization");
            return $recipient;
        }

        // Construct the normalized email by removing the +SOL_ID part
        $normalized_email = substr($local_part, 0, $plus_position) . '@' . $domain;
        $user_id = get_user_by('login', $possible_sol_id)->ID ?? null;
        
        Logger::debug(LogType::MAIL, "Email '{$email}' is identified as a plus-addressed alias for SOL ID '{$possible_sol_id}' belonging to user ID {$user_id} and will be normalized to '{$normalized_email}'", $user_id, $possible_sol_id);
        // Replace the original email in the recipient string with the normalized email
        return str_replace($email, $normalized_email, $recipient);
    }

    /**
     * Extract a valid email address from recipient text.
     *
     * Supports common formats like "email@example.com" and "Name <email@example.com>".
     * Returns null when no valid email can be extracted.
     *
     * @param string $recipient Mail recipient value
     * @return string|null Extracted email when valid, otherwise null
     */
    private static function scouting_oidc_mail_extract_valid_email_from_recipient(string $recipient): ?string {
        // First, prefer the angle-bracket format: Name <email@example.com>
        if (preg_match('/<([^<>]+)>/', $recipient, $matches)) {
            $candidate = trim($matches[1]);
            if (is_email($candidate)) {
                Logger::debug(LogType::MAIL, "Email '{$candidate}' extracted from angle-bracket format in recipient: '{$recipient}'");
                return $candidate;
            }
        }

        // Then parse simple recipient lists and validate each candidate with WordPress
        $tokens = wp_parse_list($recipient);
        foreach ($tokens as $token) {
            $candidate = trim((string) $token, " \t\n\r\0\x0B\"'<>();");
            if (is_email($candidate)) {
                Logger::debug(LogType::MAIL, "Email '{$candidate}' extracted from token list in recipient: '{$recipient}'");
                return $candidate;
            }
        }

        // Finally, allow direct single-value recipients that are already clean
        $recipient_trimmed = trim($recipient);
        if (is_email($recipient_trimmed)) {
            Logger::debug(LogType::MAIL, "Email '{$recipient_trimmed}' is a direct valid email in recipient: '{$recipient}'");
            return $recipient_trimmed;
        }

        Logger::debug(LogType::MAIL, "No valid email could be extracted from recipient: '{$recipient}'");
        return null;
    }

    /**
     * Determine whether a SOL ID belongs to a Scouting OIDC user, with request-level cache.
     *
     * @param string $sol_id SOL ID
     * @return bool True when SOL ID exists and is marked as scouting_oidc_user=true
     */
    private static function scouting_oidc_mail_is_sol_oidc_user(string $sol_id): bool {
        if (isset(self::$scouting_oidc_mail_sol_user_cache[$sol_id])) {
            Logger::debug(LogType::MAIL, "Cache hit for SOL ID '{$sol_id}': " . (self::$scouting_oidc_mail_sol_user_cache[$sol_id] ? 'is a Scouting OIDC user' : 'is not a Scouting OIDC user'));
            return self::$scouting_oidc_mail_sol_user_cache[$sol_id];
        }

        Logger::debug(LogType::MAIL, "Cache miss for SOL ID '{$sol_id}'; performing database lookup to determine if it belongs to a Scouting OIDC user");

        $user = get_user_by('login', $sol_id);
        $is_sol_oidc_user = $user !== false && get_user_meta($user->ID, 'scouting_oidc_user', true) === 'true';

        Logger::debug(LogType::MAIL, "Database lookup result for SOL ID '{$sol_id}': " . ($is_sol_oidc_user ? 'is a Scouting OIDC user' : 'is not a Scouting OIDC user'), $user->ID ?? null, $sol_id);

        self::$scouting_oidc_mail_sol_user_cache[$sol_id] = (bool) $is_sol_oidc_user;

        return $is_sol_oidc_user;
    }
}
?>