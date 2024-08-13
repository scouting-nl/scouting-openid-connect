=== Scouting OpenID Connect ===
Contributors: jobvk
Tags: scouting, scouting nederland, sol, openid connect, oidc
Requires at least: 6.4.3
Tested up to: 6.6.1
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

1. Go to https://login.scouting.nl and go to the tab "Managed websites".
2. Click on "Add OpenID Connect connection".
3. Enter the group name or name of the website.
4. Enter the domain of the website, example: https://www.example.com/ (make sure to include the trailing slash).
5. Select scopes you want to allow access to. The required scopes are "openid" and "email".
6. Select the organisations you want to allow access to.
7. Click on "Update website".
8. Click on circle with i in it to get the Client ID, Client Secret and Scopes.
9. Install the plugin on your WordPress website.
10. Go to the settings page of the plugin.
11. Enter the Client ID, Client Secret and Scopes.
12. Save the settings.
13. You are now ready to login with Scouting Nederland OpenID Connect.

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

== Changelog ==

= 0.0.1 =
Initial release

== Upgrade Notice ==

= 0.0.1 =
Initial release