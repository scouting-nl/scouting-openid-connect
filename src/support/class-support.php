<?php
/**
 * Scouting OpenID Connect plugin file
 *
 * @package ScoutingOIDC
 * @since 1.0.0
 */

namespace ScoutingOIDC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the Scouting OIDC support submenu, including registering and rendering
 * the support page.
 *
 * @since 1.0.0
 */
class Support {

	/**
	 * Registers the support submenu page under the main menu.
	 *
	 * @since 1.0.0
	 */
	public function scouting_oidc_support_submenu_page(): void {
		add_submenu_page(
			'scouting-oidc-settings',
			'Support',
			'Support',
			'manage_options',
			'scouting-oidc-support',
			array( $this, 'scouting_oidc_support_page_callback' ),
			3
		);
	}

	/**
	 * Renders the support page content.
	 *
	 * @since 1.0.0
	 * @since Unreleased Links to the plugin documentation.
	 */
	public function scouting_oidc_support_page_callback(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Support', 'scouting-openid-connect' ); ?></h1>
			<h2><?php esc_html_e( 'Documentation', 'scouting-openid-connect' ); ?></h2>
			<p><a href="<?php echo esc_url( 'https://github.com/scouting-nl/scouting-openid-connect/wiki/' ); ?>"><?php esc_html_e( 'Read the plugin documentation', 'scouting-openid-connect' ); ?></a></p>

			<h2><?php esc_html_e( 'Setting up OpenID Connect', 'scouting-openid-connect' ); ?></h2>
			<p><?php esc_html_e( 'Before you start make sure you have the role "webmaster" in', 'scouting-openid-connect' ); ?>
				<a href="https://mijn.scouting.nl" target="_blank">mijn.scouting.nl</a>.
			</p>

			<ol>
				<li><?php esc_html_e( 'Go to', 'scouting-openid-connect' ); ?> <a href="https://login.scouting.nl" target="_blank">login.scouting.nl</a>, <?php esc_html_e( 'click on "Managed websites" and click on "Add OpenID Connect connection".', 'scouting-openid-connect' ); ?></li>
				<li><?php esc_html_e( 'Add the name of your group/website.', 'scouting-openid-connect' ); ?></li>
				<li><?php esc_html_e( 'Add the Redirect URI:', 'scouting-openid-connect' ); ?> <code><?php echo esc_url( home_url( '/' ) ); ?></code></li>
				<li><?php esc_html_e( 'Add the Post Logout Redirect URI:', 'scouting-openid-connect' ); ?> <code><?php echo esc_url( home_url( '/' ) ); ?></code></li>
				<li><?php esc_html_e( 'Select the scopes you want to use. The `Email`, `Personal` and `Membership` scopes are required;', 'scouting-openid-connect' ); ?>
					<br><?php esc_html_e( 'The `Address`, `Phone number`, and `Roles` scopes are optional.', 'scouting-openid-connect' ); ?>
					<br><?php esc_html_e( 'Currently the `Parents/guardians` scope is not supported.', 'scouting-openid-connect' ); ?></li>
				<li><?php esc_html_e( 'Select the organizations that can log in.', 'scouting-openid-connect' ); ?>
					<br><?php esc_html_e( 'If your organization has sub-organizations, you can also select "Allow suborganizations".', 'scouting-openid-connect' ); ?></li>
				<li><?php esc_html_e( 'Select to use the PKCE (code challenge).', 'scouting-openid-connect' ); ?></li>
				<li><?php esc_html_e( 'Press "Add Website".', 'scouting-openid-connect' ); ?></li>
				<li><?php esc_html_e( 'Find the website you just created and click on ⓘ.', 'scouting-openid-connect' ); ?></li>
				<li><?php esc_html_e( 'Copy the "Client ID", "Client Secret", and the "Scopes" to the', 'scouting-openid-connect' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=scouting-oidc-settings' ) ); ?>">settings page</a>.
				</li>
				<li>
					<?php esc_html_e( 'Fill in the OpenID Connect Settings with the copied data.', 'scouting-openid-connect' ); ?>
					<br><?php esc_html_e( 'Required scopes:', 'scouting-openid-connect' ); ?>
					<ul style="list-style: disc; margin-left: 20px;">
						<li>openid</li>
						<li>membership</li>
						<li>profile</li>
						<li>email</li>
					</ul>
					<?php esc_html_e( 'Optional scopes:', 'scouting-openid-connect' ); ?>
					<ul style="list-style: disc; margin-left: 20px;">
						<li>address</li>
						<li>phone</li>
						<li>roles</li>
					</ul>
				</li>
				<li><?php esc_html_e( 'Fill in the General Settings.', 'scouting-openid-connect' ); ?></li>
				<li><?php esc_html_e( 'Press "Save Settings"', 'scouting-openid-connect' ); ?>.</li>
				<li><?php esc_html_e( 'Log out and try to log in with the Scouts Login button.', 'scouting-openid-connect' ); ?></li>
			</ol>

			<h2><?php esc_html_e( 'Profile and access synchronization', 'scouting-openid-connect' ); ?></h2>
			<p><?php esc_html_e( 'Scouting OpenID Connect account names and email addresses are refreshed during sign-in. Birth date, gender, phone number, and address are synchronized only when enabled in General Settings and the corresponding OIDC scopes are configured. These optional values are not refreshed continuously.', 'scouting-openid-connect' ); ?></p>
			<p><?php esc_html_e( 'Users should update profile information in Scouts Online. Disabling an optional field stops refreshing its SOL profile metadata but does not erase previously stored values; if WooCommerce synchronization remains enabled, stored values may still be copied to WooCommerce. For an enabled field, a blank SOL value clears the plugin’s stored value at the next sign-in, but WooCommerce synchronization does not replace existing billing or shipping values with blank values.', 'scouting-openid-connect' ); ?></p>
			<p><?php esc_html_e( 'When the roles scope is enabled, role assignments returned by SOL are synchronized and the configured role mapping is applied during sign-in. A matching deny rule blocks that sign-in but does not end an existing WordPress session. Removing a member from SOL does not automatically delete their WordPress account or its stored data; review and remove accounts according to your site’s data-retention policy.', 'scouting-openid-connect' ); ?></p>

			<h2><?php esc_html_e( 'Support', 'scouting-openid-connect' ); ?></h2>
			<p><?php esc_html_e( 'If you need help, please contact', 'scouting-openid-connect' ); ?>
				<a href="mailto:cms@support.scouting.nl?cc=job.van.koeveringe@scouting.nl&subject=WordPress%20Scouting%20OIDC%20Support" target="_blank">cms@support.scouting.nl & job.van.koeveringe@scouting.nl</a>
				<?php esc_html_e( '(developer of the plugin)', 'scouting-openid-connect' ); ?>.
			</p>
		</div>
		<?php
	}
}
