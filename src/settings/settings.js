/**
 * Manages dynamic controls on the OpenID Connect settings page.
 *
 * @file  Defines dynamic controls for the OpenID Connect settings page.
 * @since 2.4.0
 */

/**
 * Initializes settings controls after the DOM is ready.
 *
 * @since 2.4.0
 */
document.addEventListener( 'DOMContentLoaded', function() {
	/**
	 * Toggles visibility of the custom redirect setting.
	 *
	 * @since  2.4.0
	 * @return {void} Does not return a value.
	 */
	function toggleCustomRedirect() {
		const select = document.getElementById( 'scouting_oidc_login_redirect' );
		const customRow = document.querySelector( '.scouting-oidc-custom-redirect-tr' );
		if ( select === null || customRow === null ) {
			return;
		}

		customRow.style.display = select.value === 'custom' ? '' : 'none';
	}

	/**
	 * Shows or hides a settings field from dependent control values.
	 *
	 * @since  2.4.0
	 * @param {string}          fieldTrClass      CSS selector for the field row.
	 * @param {string|string[]} conditionFieldIds IDs of the dependent controls.
	 * @return {void} Does not return a value.
	 */
	function showField( fieldTrClass, conditionFieldIds ) {
		const ids = Array.isArray( conditionFieldIds ) ? conditionFieldIds : [ conditionFieldIds ];
		const fieldRow = document.querySelector( fieldTrClass );
		if ( fieldRow === null ) {
			return;
		}

		const shouldShow = ids.some( function( id ) {
			const field = document.getElementById( id );
			return field && field.checked;
		} );

		fieldRow.style.display = shouldShow ? '' : 'none';
	}

	/**
	 * Configures client-secret visibility controls.
	 *
	 * @since  2.4.0
	 * @return {void} Does not return a value.
	 */
	function setupClientSecretToggle() {
		const input = document.getElementById( 'scouting_oidc_client_secret' );
		const toggle = document.getElementById( 'scouting_oidc_client_secret_toggle' );
		if ( input === null || toggle === null ) {
			return;
		}

		const showText = toggle.getAttribute( 'data-show-text' ) || 'Show';
		const hideText = toggle.getAttribute( 'data-hide-text' ) || 'Hide';

		/**
		 * Synchronizes the client-secret toggle state.
		 *
		 * @since  2.4.0
		 * @return {void} Does not return a value.
		 */
		function syncToggleState() {
			const hasValue = input.value.length > 0;
			toggle.disabled = ! hasValue;

			if ( ! hasValue ) {
				input.type = 'password';
				toggle.textContent = showText;
			}
		}

		toggle.addEventListener( 'click', function() {
			const shown = input.type === 'text';
			input.type = shown ? 'password' : 'text';
			toggle.textContent = shown ? showText : hideText;
		} );

		input.addEventListener( 'input', syncToggleState );
		syncToggleState();
	}

	const select = document.getElementById( 'scouting_oidc_login_redirect' );
	const checkBox1 = document.getElementById( 'scouting_oidc_user_address' );
	const checkBox2 = document.getElementById( 'scouting_oidc_user_phone' );

	if ( select !== null && checkBox1 !== null && checkBox2 !== null ) {
		showField(
			'.scouting-oidc-user-woocommerce-sync-tr',
			[ 'scouting_oidc_user_phone', 'scouting_oidc_user_address' ],
		);
		toggleCustomRedirect();

		select.addEventListener( 'change', toggleCustomRedirect );
		checkBox1.addEventListener( 'change', function() {
			showField(
				'.scouting-oidc-user-woocommerce-sync-tr',
				[ 'scouting_oidc_user_phone', 'scouting_oidc_user_address' ],
			);
		} );
		checkBox2.addEventListener( 'change', function() {
			showField(
				'.scouting-oidc-user-woocommerce-sync-tr',
				[ 'scouting_oidc_user_phone', 'scouting_oidc_user_address' ],
			);
		} );
	}

	setupClientSecretToggle();
} );
