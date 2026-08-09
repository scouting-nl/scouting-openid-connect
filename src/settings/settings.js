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
document.addEventListener( 'DOMContentLoaded', function () {
	/**
	 * Toggles visibility of the custom redirect setting.
	 *
	 * @since  2.4.0
	 * @return {void} Does not return a value.
	 */
	function toggleCustomRedirect() {
		var select = document.getElementById( 'scouting_oidc_login_redirect' );
		var customRow = document.querySelector( '.scouting-oidc-custom-redirect-tr' );
		if ( select === null || customRow === null ) {
			return;
		}

		customRow.style.display = select.value === 'custom' ? '' : 'none';
	}

	/**
	 * Shows or hides a settings field from dependent control values.
	 *
	 * @since  2.4.0
	 * @param  {string}          fieldTrClass      CSS selector for the field row.
	 * @param  {string|string[]} conditionFieldIds IDs of the dependent controls.
	 * @return {void} Does not return a value.
	 */
	function showField( fieldTrClass, conditionFieldIds ) {
		var ids = Array.isArray( conditionFieldIds ) ? conditionFieldIds : [ conditionFieldIds ];
		var fieldRow = document.querySelector( fieldTrClass );
		if ( fieldRow === null ) {
			return;
		}

		var shouldShow = ids.some( function ( id ) {
			var field = document.getElementById( id );
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
		var input = document.getElementById( 'scouting_oidc_client_secret' );
		var toggle = document.getElementById( 'scouting_oidc_client_secret_toggle' );
		if ( input === null || toggle === null ) {
			return;
		}

		var showText = toggle.getAttribute( 'data-show-text' ) || 'Show';
		var hideText = toggle.getAttribute( 'data-hide-text' ) || 'Hide';

		/**
		 * Synchronizes the client-secret toggle state.
		 *
		 * @since  2.4.0
		 * @return {void} Does not return a value.
		 */
		function syncToggleState() {
			var hasValue = input.value.length > 0;
			toggle.disabled = ! hasValue;

			if ( ! hasValue ) {
				input.type = 'password';
				toggle.textContent = showText;
			}
		}

		toggle.addEventListener( 'click', function () {
			var shown = input.type === 'text';
			input.type = shown ? 'password' : 'text';
			toggle.textContent = shown ? showText : hideText;
		} );

		input.addEventListener( 'input', syncToggleState );
		syncToggleState();
	}

	var select = document.getElementById( 'scouting_oidc_login_redirect' );
	var checkBox1 = document.getElementById( 'scouting_oidc_user_address' );
	var checkBox2 = document.getElementById( 'scouting_oidc_user_phone' );

	if ( select !== null && checkBox1 !== null && checkBox2 !== null ) {
		showField(
			'.scouting-oidc-user-woocommerce-sync-tr',
			[ 'scouting_oidc_user_phone', 'scouting_oidc_user_address' ]
		);
		toggleCustomRedirect();

		select.addEventListener( 'change', toggleCustomRedirect );
		checkBox1.addEventListener( 'change', function () {
			showField(
				'.scouting-oidc-user-woocommerce-sync-tr',
				[ 'scouting_oidc_user_phone', 'scouting_oidc_user_address' ]
			);
		} );
		checkBox2.addEventListener( 'change', function () {
			showField(
				'.scouting-oidc-user-woocommerce-sync-tr',
				[ 'scouting_oidc_user_phone', 'scouting_oidc_user_address' ]
			);
		} );
	}

	setupClientSecretToggle();
} );