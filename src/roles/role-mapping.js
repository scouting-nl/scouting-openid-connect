/**
 * Progressive enhancements for the SOL mapping rule editor.
 *
 * @since 2.7.0 Adds progressive dialogs, live search and wrapping native checkbox choices.
 */

/**
 * Initializes searches and enhances multi-selects with native checkbox groups.
 *
 * @since 2.7.0 Filters options live, preserving selections and the no-JavaScript multi-select fallback.
 * @return {void}
 */
function scoutingOidcInitializeRoleMapping() {
	scoutingOidcInitializeRuleSubmission();
	const organisation = document.getElementById( 'sol-organisation' );
	if ( ! organisation ) {
		return;
	}

	/**
	 * Prevents repeat submissions while allowing native validation and back navigation.
	 *
	 * @since 2.7.0 Guards mapping saves against rapid clicks and repeated Enter presses.
	 * @return {void}
	 */
	function scoutingOidcInitializeRuleSubmission() {
		const form = document.getElementById( 'sol-rule-form' );
		if ( ! form ) {
			return;
		}
		const submit = form.querySelector( '[type="submit"]' );
		let submitting = false;
		form.addEventListener( 'submit', ( event ) => {
			if ( submitting ) {
				event.preventDefault();
				return;
			}
			submitting = true;
			submit.disabled = true;
			form.setAttribute( 'aria-busy', 'true' );
		} );
		window.addEventListener( 'pageshow', () => {
			submitting = false;
			submit.disabled = false;
			form.removeAttribute( 'aria-busy' );
		} );
	}

	const unit = document.getElementById( 'sol-unit' );
	const role = document.getElementById( 'sol-role' );
	const selectors = [ organisation, unit, role ];
	const operators = [
		document.getElementById( 'sol-organisation-operator' ),
		document.getElementById( 'sol-organisation_unit-operator' ),
		document.getElementById( 'sol-role-operator' ),
	];
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
	 * Wrapping checkbox groups backed by the original named selects.
	 *
	 * @since 2.7.0 Preserves submitted values while improving readability and touch selection.
	 * @type {HTMLDivElement[]}
	 */
	const choiceGroups = selectors.map( ( select, index ) => {
		const label = select.labels[ 0 ];
		const heading = document.createElement( 'span' );
		heading.id = select.id + '-label';
		heading.textContent = label.textContent;
		const group = document.createElement( 'div' );
		group.id = select.id + '-choices';
		group.className = 'scouting-oidc-checkbox-choices';
		group.setAttribute( 'role', 'group' );
		group.setAttribute( 'aria-labelledby', heading.id );
		group.setAttribute( 'aria-describedby', 'sol-condition-help ' + select.id + '-results' );
		choices[ index ].forEach( ( option ) => {
			const row = document.createElement( 'label' );
			const checkbox = document.createElement( 'input' );
			checkbox.type = 'checkbox';
			checkbox.value = option.value;
			checkbox.checked = option.selected;
			const text = document.createElement( 'span' );
			text.textContent = option.textContent;
			row.append( checkbox, text );
			group.append( row );
		} );
		group.addEventListener( 'change', ( event ) => {
			const checkbox = event.target;
			if ( checkbox instanceof HTMLInputElement ) {
				const option = Array.from( select.options ).find(
					( item ) => item.value === checkbox.value,
				);
				option.selected = checkbox.checked;
				select.dispatchEvent( new Event( 'change' ) );
			}
		} );
		group.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				searches[ index ].focus();
			}
		} );
		label.replaceWith( heading );
		select.after( group );
		select.hidden = true;
		searches[ index ].setAttribute( 'aria-controls', group.id );
		searches[ index ].parentElement.querySelector( '.sol-clear-selection' )
			.setAttribute( 'aria-controls', group.id );
		return group;
	} );

	/**
	 * Moves from search to the first visible choice, if any.
	 *
	 * @since 2.7.0 Keeps search and checkbox choices keyboard operable.
	 * @param {number} index Selector index.
	 * @return {void}
	 */
	function focusChoice( index ) {
		const checkbox = choiceGroups[ index ].querySelector( 'label:not([hidden]) input' );
		( checkbox || searches[ index ] ).focus();
	}

	/**
	 * Rebuilds a multi-selector without dropping selected values during filtering.
	 *
	 * @since 2.7.0 Displays matching choices as a list while searching.
	 * @param {number} index Selector index.
	 * @return {number} Number of matching choices.
	 */
	function filterSelector( index ) {
		const select = selectors[ index ];
		const selected = Array.from( select.selectedOptions, ( option ) => option.value );
		const organisations = Array.from( organisation.selectedOptions, ( option ) => option.value );
		const units = Array.from( unit.selectedOptions, ( option ) => option.value );
		const search = searches[ index ].value.trim().toLocaleLowerCase();
		let count = 0;
		select.replaceChildren();
		choices[ index ].forEach( ( option ) => {
			const ancestry = ( option.dataset.organisations || '' ).split( ' ' );
			const compatible =
				( index === 0 ||
					organisations.length === 0 ||
					( operators[ 0 ].value === 'not_in'
						? ! organisations.some( ( id ) => ancestry.includes( id ) )
						: organisations.some( ( id ) => ancestry.includes( id ) ) ) ) &&
				( index !== 2 ||
					units.length === 0 ||
					( operators[ 1 ].value === 'not_in'
						? ! units.includes( option.dataset.unit )
						: units.includes( option.dataset.unit ) ) );
			const matches = option.textContent
				.toLocaleLowerCase()
				.includes( search );
			const isSelected = selected.includes( option.value );
			if ( ( compatible && matches ) || isSelected ) {
				const clone = option.cloneNode( true );
				clone.selected = isSelected;
				select.append( clone );
				if ( compatible && matches ) {
					count += 1;
				}
			}
		} );
		select.size = 6;
		const visibleOptions = new Map(
			Array.from( select.options, ( option ) => [ option.value, option ] ),
		);
		choiceGroups[ index ].querySelectorAll( 'input' ).forEach( ( checkbox ) => {
			const option = visibleOptions.get( checkbox.value );
			checkbox.checked = Boolean( option && option.selected );
			checkbox.parentElement.hidden = ! option;
			if ( ! option && checkbox === checkbox.ownerDocument.activeElement ) {
				searches[ index ].focus();
			}
		} );
		document.getElementById( select.id + '-results' ).textContent = search
			? resultTemplate.replace( '{count}', count ).replace(
				'{selection}', Array.from( select.selectedOptions, ( option ) => option.textContent ).join( ', ' ),
			)
			: '';
		return count;
	}

	/**
	 * Applies parent filters first and announces the available choices.
	 *
	 * @since 2.7.0 Provides accessible search feedback.
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
		searches[ index ].parentElement.querySelector( '.sol-clear-selection' )
			.addEventListener( 'click', () => {
				Array.from( select.options ).forEach( ( option ) => {
					option.selected = false;
				} );
				refresh( true );
				focusChoice( index );
			} );
		searches[ index ].addEventListener( 'input', () => refresh( true ) );
		searches[ index ].addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'ArrowDown' || event.key === 'Enter' ) {
				event.preventDefault();
				focusChoice( index );
			}
		} );
		select.addEventListener( 'change', () => refresh( true ) );
		operators[ index ].addEventListener( 'change', () => refresh( true ) );
	} );
	refresh( false );

	const control = document.getElementById( 'login-allowed' );
	const container = document.getElementById( 'sol-wordpress-role-row' );

	/**
	 * Keeps the stored role submitted even when login is denied.
	 *
	 * @since 2.7.0 Hides irrelevant role selection without changing data.
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
 * @since 2.7.0 Opens add, edit and duplicate flows in a modal.
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
 * @since 2.7.0 Shares accessible dialogs between editing and user testing.
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
	 * @since 2.7.0 Restores focus for add and automatically opened editors.
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

/**
 * Hides inline dialog panels before rendering while retaining failure fallback.
 *
 * @since 2.7.0 Prevents editor and tester flashes during page loading.
 */
if ( typeof document.createElement( 'dialog' ).showModal === 'function' ) {
	document.documentElement.classList.add( 'scouting-oidc-dialogs-pending' );
}

/**
 * Initializes the completed markup and restores any unenhanced inline panels.
 *
 * @since 2.7.0 Preserves inline fallback even when initialization throws.
 * @return {void}
 */
function scoutingOidcInitializeRoleMappingWhenReady() {
	try {
		scoutingOidcInitializeRoleMapping();
	} finally {
		document.documentElement.classList.remove( 'scouting-oidc-dialogs-pending' );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener(
		'DOMContentLoaded',
		scoutingOidcInitializeRoleMappingWhenReady,
		{ once: true },
	);
} else {
	scoutingOidcInitializeRoleMappingWhenReady();
}
