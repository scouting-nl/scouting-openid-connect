=== Scouting OpenID Connect ===
Contributors: jobvk
Tags: scouting, scouting nederland, sol, openid connect, oidc
Requires at least: 6.6.0
Tested up to: 6.9
Stable tag: 2.1.0
Requires PHP: 8.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WordPress plugin for logging in with Scouting Nederland OpenID Connect Server.

== Description ==

A WordPress plugin for logging in with Scouting Nederland OpenID Connect Server.

This plugin allows users to authenticate and login to their WordPress websites using their Scouting Nederland OpenID Connect credentials.
It provides a secure and convenient way for Scouting Nederland members to access their WordPress sites without the need for separate login credentials. 
With this plugin, users can seamlessly integrate their Scouting Nederland accounts with their WordPress websites, enhancing the user experience and simplifying the login process.

== Installation ==

Make sure you have the role `webmaster` in [mijn.scouting.nl](https://mijn.scouting.nl).

1. Go to [https://login.scouting.nl](https://login.scouting.nl), click on `Managed websites` and click on `Add OpenID Connect connection`.
2. Add the name of your group/website.
3. Add the Redirect URI, for example: https://example.com/.
4. Add the Post Logout Redirect URI, for example: https://example.com/.
5. Select the scopes you want to use. The "email" and "membership" scopes are required; the "profile" scope is optional.
6. Select the organizations that can log in. If your organization has sub-organizations, you can also select `Allow suborganizations.`
7. Press `Add Website.`
8. Find the website you just created and click on ⓘ.
9. Copy the client ID, client secret, and the scopes to your website.
10. Fill in the OpenID Connect Settings with the copied data. Make sure the required scopes, "openid" , "email" and "membership", are present.
11. Fill in the General Settings. If you want to store the birthdate, or gender, use the scope "profile".
12. Press `Save Settings.`
13. Log out and try to log in with the Scouts Login button.

== Frequently Asked Questions ==

= Do i need to be part of Scouting Nederland to use this? =

Yes, the OpenID Connect server is used to identify people and only allows access when they are members of the appropriate organization within Scouting Nederland.
To set up the system at Scouting Nederland, you need webmaster privileges for your scouting group.

= Are there settings for this plugin? =

Yes, there is a settings page where you can set up a redirect after login, configure the name the user gets in their profile, and enforce that.

= Can roles also be imported into WordPress from SOL? =

Currently not, but this is planned for a future update of this plugin.

== Screenshots ==

1. Login Page
2. Settings Page
3. Shortcode Page
4. Support Page

== Changelog ==

= 2.1.0 =
* Tested up to: `6.9`
* Add logout redirect host allowlist handling in `scouting_oidc_auth_logout_redirect()` to permit external logout URLs.
* Clear user cache after username updates (`clean_user_cache`) to avoid stale user data.
* Trigger core `wp_login` and plugin-specific `scouting_oidc_wp_login` actions when programmatically logging in.

= 2.0.1 =
* Make upgrading from `1.2.0` to `2.0.0` backwards compatible.

= 2.0.0 =
* Use version `2.0.1` instead for backward compatibility with version `1.2.0`.
* `membership` scope is now required to obtain the SOL Member ID.
* `infix` is removed from user this was conflicting with WooCommerce, the infix is now added before the last name.
* Removed the `prefix` field from the general settings.
* Removed the SOL ID field profile this is now the UserName of the WordPress User. 
* Improved error messages and redirects for missing or invalid user data or OIDC scopes.
* Updated setup and support documentation to reflect the new identification model.

= 1.2.0 =
* Add custom redirect option for successful login.

= 1.1.0 =
* Add option to redirect only SOL users.

= 1.0.2 =
* Tested plugin up to WordPress 6.7.2 => 6.8.0

= 1.0.1 =
* Fixed hook wp_login
* Tested plugin up to WordPress 6.7.1 => 6.7.2

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 2.0.0 =
⚠️ `Breaking Changes`

This release introduces a major change in how WordPress users are identified.  
The WordPress `UserName` now uses the `SOL ID` instead of the `SOL UserName`.

Use version `2.0.1` for backward compatibility with version `1.2.0`.

= 1.0.0 =
* Initial release