# Change Log

All notable changes to this project will be documented in this file.

## [[2.6.2](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.6.2)] - 26/08/2026

- Preserved historical log entries when WordPress users are deleted.
- Added audit logging for SOL user deletions, including the initiating user or system process.
- Limited log user ID profile links to WordPress users that still exist.

## [[2.6.1](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.6.1)] - 25/08/2026

- Fixed OIDC session creation before WordPress login form output.
- Improved authentication redirect validation to require HTTPS URLs.

## [[2.6.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.6.0)] - 13/08/2026

- Refactored the plugin's PHP and JavaScript codebase for improved readability and consistency.
- Standardized source file names and the internal plugin structure.
- Tested up to: `7.1`.

## [[2.5.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.5.0)] - 07/08/2026

- Added a `subject` property to the User class to store the OpenID Connect subject, enforced that it must be present.
- Fetch user claims from the discovered OpenID Connect UserInfo endpoint instead of the ID token.
- Add a "View in SOL" action to Scouting OpenID Connect users in the WordPress Users table.
- Add a Site Health check to verify the OpenID Connect provider's health and configuration.
- Fix cron job scheduling for clearing old logs to ensure it runs as expected.
- Added Code of Conduct, Contributing and Security policy to the plugin distribution.
- Tested up to: `7.0`.

## [[2.4.1](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.4.1)] - 30/04/2026

- Fixed some spelling mistakes.

## [[2.4.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.4.0)] - 30/04/2026

- Added logout support and dedicated handling for the OpenID Connect logout flow.
- Redefined the nonce-based login flow to harden authentication callbacks.
- Added logging pages and utilities to inspect plugin activity and support troubleshooting.
- Added redirect_back to current page for both shortcodes.

## [[2.3.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.3.0)] - 16/02/2026

- Added a settings option to define how duplicated emails should be handled: plus addressing or return an error.
- When sending an email via WordPress, the plus addressing is removed so the user does not see it.
- Fixed fallback conditions for phone and address fields in WooCommerce.
- Added a custom hook for new user registration: `scouting_oidc_user_register`.

## [[2.2.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.2.0)] - 10/02/2026

- Add support for `address` and `phone` scopes to store address data and phone number in user profiles.
- Require PKCE (Proof Key for Code Exchange) to be configured in OIDC.
- Update default scopes to include `address` and `phone` in addition to existing scopes.
- Add WooCommerce integration to automatically sync user data (name, phone, address) to WooCommerce billing and shipping fields.
- Hide phone and address fields from user profile when WooCommerce is active to prevent duplication.
- Improve user profile field rendering with `readonly` instead of `disabled` for better accessibility.

## [[2.1.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.1.0)] - 02/12/2025

- Tested up to: 6.9
- Add logout redirect host allowlist handling in `scouting_oidc_auth_logout_redirect()` to permit external logout URLs.
- Clear user cache after username updates (`clean_user_cache`) to avoid stale user data.
- Trigger core `wp_login` and plugin-specific `scouting_oidc_wp_login` actions when programmatically logging in.

## [[2.0.1](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.0.1)] - 22/10/2025

- Make upgrading from `1.2.0` to `2.0.0` backwards compatible.

## [[2.0.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.0.0)] - 10/10/2025

⚠️ Breaking Changes

For backward compatibility with version `1.2.0`, use version `2.0.1` instead.

This release introduces a major change in how WordPress users are identified.
The WordPress `UserName` now uses the `SOL ID` instead of the `SOL UserName`.
This change was made because SOL usernames can be changed, which caused issues with Scout-In 2025.

Importent:

- The `membership` scope is now required to obtain the SOL Member ID.
- The `infix` field has been removed from the user object due to conflicts with WooCommerce. The infix is now automatically added before the last name.

Other changes:

- The `prefix` field has been removed from General Settings.
- The SOL ID field has been removed from the user profile, it is now used as the WordPress username.
- Improved error messages and redirect handling for missing or invalid user data or OIDC scopes.
- Updated setup and support documentation to reflect the new identification model.

## [[1.2.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/1.2.0)] - 14/09/2025

- Added custom redirect option for successful login.
- Improved error handling and redirects for cases where required user data or scopes are missing or invalid.

## [[1.1.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/1.1.0)] - 23/06/2025

- Added option to redirect only SOL users in settings.

## [[1.0.2](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/1.0.2)] - 20/05/2025

- Tested plugin up to WordPress 6.7.2 => 6.8.0

## [[1.0.1](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/1.0.1)] - 23/02/2025

- Fix hook [wp_login](https://developer.wordpress.org/reference/hooks/wp_login/) by adding third parameter.
- Tested plugin up to WordPress 6.7.1 => 6.7.2

## [[1.0.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/1.0.0)] - 17/12/2024

Initial release
