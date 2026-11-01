/**
 * Progressive enhancements for the SOL mapping rule editor.
 *
 * @since Unreleased Adds a modal editor and visible live search results.
 */

/**
 * Initializes searches without replacing native keyboard-operable controls.
 *
 * @since Unreleased Filters options live and progressively enhances the editor.
 * @return {void}
 */
function scoutingOidcInitializeRoleMapping() {
	const organisation = document.getElementById( 'sol-organisation' );
	if ( ! organisation ) {
		return;
	}

	const unit = document.getElementById( 'sol-unit' );
	const role = document.getElementById( 'sol-role' );
	const selectors = [ organisation, unit, role ];
	const searches = selectors.map( ( select ) =>
		document.getElementById( select.id + '-search' ),
	);
	const choices = selectors.map( ( select ) =>
		Array.from( select.options ).map( ( option ) => option.cloneNode( true ) ),
	);
	const status = document.getElementById( 'sol-search-status' );
	const template = document.getElementById(
		'sol-search-status-template',
	).textContent;
	const resultTemplate = document.getElementById(
		'sol-search-result-template',
	).textContent;

	/**
	 * Rebuilds one selector; incompatible values reset, search alone never clears.
	 *
	 * @since Unreleased Displays matching choices as a list while searching.
	 * @param {number} index Selector index.
	 * @return {number} Number of matching non-wildcard choices.
	 */
	function filterSelector( index ) {
		const select = selectors[ index ];
		const selected = select.value;
		const search = searches[ index ].value.trim().toLocaleLowerCase();
		let count = 0;
		select.replaceChildren();
		choices[ index ].forEach( ( option, optionIndex ) => {
			const compatible =
				( index === 0 ||
					! organisation.value ||
					( option.dataset.organisations || '' )
						.split( ' ' ).includes( organisation.value ) ) &&
				( index !== 2 ||
					unit.value === '0' ||
					option.dataset.unit === unit.value );
			const matches = option.textContent
				.toLocaleLowerCase()
				.includes( search );
			if ( optionIndex === 0 || ( compatible && matches ) ) {
				select.append( option.cloneNode( true ) );
				if ( optionIndex > 0 ) {
					count += 1;
				}
			} else if ( compatible && option.value === selected ) {
				select.append( option.cloneNode( true ) );
			}
		} );
		if ( Array.from( select.options ).some( ( option ) => option.value === selected ) ) {
			select.value = selected;
		} else {
			select.selectedIndex = 0;
		}
		select.size = search ? Math.max( 2, Math.min( 6, select.options.length ) ) : 1;
		document.getElementById( select.id + '-results' ).textContent = search
			? resultTemplate.replace( '{count}', count ).replace(
				'{selection}', select.selectedOptions[ 0 ].textContent,
			)
			: '';
		return count;
	}

	/**
	 * Applies parent filters first and announces the available choices.
	 *
	 * @since Unreleased Provides accessible search feedback.
	 * @param {boolean} announce Whether to update the live status.
	 * @return {void}
	 */
	function refresh( announce ) {
		const counts = selectors.map( ( select, index ) => filterSelector( index ) );
		if ( announce ) {
			status.textContent = template
				.replace( '{organisations}', counts[ 0 ] )
				.replace( '{units}', counts[ 1 ] )
				.replace( '{roles}', counts[ 2 ] );
		}
	}

	selectors.forEach( ( select, index ) => {
		searches[ index ].parentElement.hidden = false;
		searches[ index ].addEventListener( 'input', () => refresh( true ) );
		searches[ index ].addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'ArrowDown' || event.key === 'Enter' ) {
				event.preventDefault();
				select.focus();
			}
		} );
		select.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' && select.size > 1 ) {
				event.preventDefault();
				searches[ index ].focus();
			}
		} );
		select.addEventListener( 'change', () => refresh( true ) );
	} );
	refresh( false );

	const control = document.getElementById( 'login-allowed' );
	const container = document.getElementById( 'sol-wordpress-role-row' );

	/**
	 * Keeps the stored role submitted even when login is denied.
	 *
	 * @since Unreleased Hides irrelevant role selection without changing data.
	 * @return {void}
	 */
	function updateOutcome() {
		container.hidden = control.value === '0';
	}
	control.addEventListener( 'change', updateOutcome );
	updateOutcome();

	scoutingOidcInitializeRuleDialog();
	const testPanel = document.getElementById( 'sol-user-test-panel' );
	const submitted = testPanel.dataset.submitted === 'true';
	scoutingOidcInitializeDialog( {
		panel: testPanel,
		trigger: document.getElementById( 'sol-open-user-test' ),
		heading: document.getElementById( 'sol-user-test-heading' ),
		descriptionId: 'sol-user-test-help',
		dialogId: 'sol-user-test-dialog',
		automatic: submitted || window.location.hash === '#sol-user-test-heading' ||
			window.location.hash === '#sol-user-test-result',
		interceptTrigger: true,
		initialFocus: document.getElementById( submitted
			? 'sol-user-test-result' : 'sol-test-user' ),
	} );
}

/**
 * Moves the server-rendered editor into a native, keyboard-accessible dialog.
 *
 * @since Unreleased Opens add, edit and duplicate flows in a modal.
 * @return {void}
 */
function scoutingOidcInitializeRuleDialog() {
	const panel = document.getElementById( 'sol-rule-panel' );
	const editing = panel.dataset.editing === 'true';
	scoutingOidcInitializeDialog( {
		panel,
		trigger: document.getElementById( 'sol-add-rule' ),
		heading: document.getElementById( 'scouting-oidc-rule-editor' ),
		descriptionId: 'sol-condition-help',
		dialogId: 'sol-rule-dialog',
		automatic: editing || window.location.hash === '#scouting-oidc-rule-editor',
		interceptTrigger: ! editing,
		initialFocus: document.getElementById( 'scouting-oidc-rule-editor' ),
	} );
}

/**
 * Enhances an inline panel with shared modal and focus behavior.
 *
 * @since Unreleased Shares accessible dialogs between editing and user testing.
 * @param {Object} options Dialog elements, identifiers and opening behavior.
 * @return {void}
 */
function scoutingOidcInitializeDialog( options ) {
	const dialog = document.createElement( 'dialog' );
	if ( typeof dialog.showModal !== 'function' ) {
		if ( options.automatic ) {
			options.initialFocus.focus();
		}
		return;
	}

	const { panel, trigger, heading, initialFocus } = options;
	dialog.id = options.dialogId;
	dialog.className = 'scouting-oidc-rule-dialog scouting-oidc-role-mapping';
	dialog.setAttribute( 'aria-labelledby', heading.id );
	dialog.setAttribute( 'aria-describedby', options.descriptionId );
	panel.before( dialog );
	dialog.append( panel );
	if ( options.interceptTrigger ) {
		trigger.setAttribute( 'aria-haspopup', 'dialog' );
		trigger.setAttribute( 'aria-controls', dialog.id );
		trigger.setAttribute( 'role', 'button' );
		trigger.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			dialog.showModal();
			initialFocus.focus();
		} );
		trigger.addEventListener( 'keydown', ( event ) => {
			if ( event.key === ' ' ) {
				event.preventDefault();
				trigger.click();
			}
		} );
	}

	/**
	 * Closes the editor and immediately restores focus to the page action.
	 *
	 * @since Unreleased Restores focus for add and automatically opened editors.
	 * @return {void}
	 */
	function closeDialog() {
		dialog.close();
		trigger.focus();
	}

	panel.querySelectorAll( '.sol-close-dialog' ).forEach( ( button ) => {
		button.hidden = false;
		button.addEventListener( 'click', closeDialog );
	} );
	const fallbackCancel = panel.querySelector( '.sol-inline-cancel' );
	if ( fallbackCancel ) {
		fallbackCancel.hidden = true;
	}
	dialog.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			event.preventDefault();
			closeDialog();
		}
	} );
	dialog.addEventListener( 'close', () => trigger.focus() );
	if ( options.automatic ) {
		dialog.showModal();
		initialFocus.focus();
	}
}

scoutingOidcInitializeRoleMapping();
