=== Scouting OpenID Connect ===
Contributors: jobvk
Tags: scouting, scouting nederland, sol, openid connect, oidc
Requires at least: 6.4.3
Tested up to: 6.6.2
Stable tag: 0.0.1
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

1. Go to https://login.scouting.nl, click on "Managed websites" and click on "Add OpenID Connect connection".
2. Add the name of your group/website.
3. Add the Redirect URI, for example: https://example.com/.
4. Add the Post Logout Redirect URI, for example: https://example.com/.
5. Select the scopes you want to use. The "email" scope is required; the "profile" and "membership" scopes are optional.
6. Select the organizations that can log in. If your organization has sub-organizations, you can also select `Allow suborganizations.`
7. Press `Add Website.`
8. Find the website you just created and click on ⓘ.
9. Copy the `Client ID`, `Client Secret`, and the `Scopes` to your website.
10. Fill in the OpenID Connect Settings with the copied data. Make sure the required scopes, "openid" and "email", are present.
11. Fill in the General Settings. If you want to store the name, birthdate, or gender, use the scope "profile". If you also want the SOL ID, use the scope "membership".
12. Press "Save Settings."
13. Log out and try to log in with the Scouts Login button.

== Frequently Asked Questions ==

= Do i need to be part of Scouting Nederland to use this? =

Yes, the OpenID Connect server is used to identify people and only allows access when they are members of the appropriate organization within Scouting Nederland.
To set up the system at Scouting Nederland, you need webmaster privileges for your scouting group.

= Are there settings for this plugin? =

Yes, there is a settings page where you can set up a redirect after login or logout, configure the name the user gets in their profile, and enforce that.

= Can roles also be imported into WordPress from SOL? =

Currently not, but this is planned for a future update of this plugin.

== Screenshots ==

1. Login Page
2. Settings Page
3. Support Page

== Changelog ==

= 0.0.1 =
Initial release

== Upgrade Notice ==

= 0.0.1 =
Initial release