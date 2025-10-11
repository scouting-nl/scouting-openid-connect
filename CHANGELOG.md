# Change Log
All notable changes to this project will be documented in this file.

## [[2.0.0](https://github.com/scouting-nl/scouting-openid-connect/releases/tag/2.0.0)] - 10/10/2025

⚠️ Breaking Changes

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