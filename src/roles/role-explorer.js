/**
 * Lazy loading SOL organisation tree for the role explorer.
 *
 * @since Unreleased Loads organisation descendants, units, and roles on demand.
 */

/**
 * Initializes the progressively loaded SOL organisation tree.
 *
 * @since Unreleased Adds lazy loading and filter links to the role explorer.
 * @return {void}
 */
function scoutingOidcInitializeRoleExplorer() {
	const tree = document.getElementById( 'sol-explorer-tree' );
	const status = document.getElementById( 'sol-explorer-status' );
	const config = window.scoutingOidcRoleExplorer;
	let childListId = 0;
	let filterRequest;

	if ( ! tree || ! status || ! config ) {
		return;
	}

	const expandedNodes = new Set( JSON.parse( tree.dataset.expandedNodes || '[]' ) );

	/**
	 * Creates a URL that filters assignments to the selected tree item.
	 *
	 * @since Unreleased Filters assignments from a tree selection.
	 * @param {Object} node Loaded tree item.
	 * @return {string} Explorer URL.
	 */
	function getFilterUrl( node ) {
		const url = new URL( config.explorerUrl, window.location.href );
		url.searchParams.set( 'organisation_id', node.organisationId );
		url.searchParams.delete( 'organisation_unit_id' );
		url.searchParams.delete( 'role_id' );
		url.searchParams.delete( 'paged' );
		if ( node.type === 'unit' || node.type === 'role' ) {
			url.searchParams.set( 'organisation_unit_id', node.unitId );
		}
		if ( node.type === 'role' ) {
			url.searchParams.set( 'role_id', node.id );
		}
		return url.toString();
	}

	/**
	 * Creates an expand button and child list for an organisation or unit.
	 *
	 * @since Unreleased Makes each tree branch independently expandable.
	 * @param {Object}        node Loaded tree item.
	 * @param {HTMLLIElement} item Parent list item.
	 * @return {HTMLUListElement} Nested child list.
	 */
	function createBranch( node, item ) {
		const childList = document.createElement( 'ul' );
		childList.id = 'sol-explorer-branch-' + childListId;
		childList.hidden = true;
		childListId += 1;
		const toggle = document.createElement( 'button' );
		toggle.className = 'scouting-oidc-explorer-toggle';
		toggle.type = 'button';
		toggle.textContent = '+';
		toggle.setAttribute( 'aria-expanded', 'false' );
		toggle.setAttribute( 'aria-controls', childList.id );
		toggle.setAttribute( 'aria-label', config.labels.expand + ' ' + node.name );
		toggle.dataset.nodeType = node.type;
		toggle.dataset.nodeId = node.id;
		toggle.dataset.organisationId = node.organisationId || node.id;
		if ( node.type === 'unit' ) {
			toggle.dataset.organisationId = node.organisationId;
		}
		item.prepend( toggle );
		item.append( childList );
		return childList;
	}

	/**
	 * Adds one loaded organisation, unit, or role to a branch.
	 *
	 * @since Unreleased Builds safe tree nodes from server data.
	 * @param {Object}           node           Loaded tree item.
	 * @param {HTMLUListElement} parentList     Parent list.
	 * @param {string}           organisationId Owning organisation ID.
	 * @return {void}
	 */
	function appendNode( node, parentList, organisationId ) {
		const item = document.createElement( 'li' );
		item.className = 'scouting-oidc-explorer-node scouting-oidc-explorer-' + node.type;
		const ownerId = node.type === 'organisation' ? node.id : organisationId;
		const linkNode = {
			type: node.type,
			id: node.id,
			organisationId: ownerId,
			unitId: node.type === 'unit' ? node.id : node.unitId,
		};
		const link = document.createElement( 'a' );
		link.href = getFilterUrl( linkNode );
		link.textContent = config.labels[ node.type ] + ': ' + node.name + ' (' + node.id + ')';
		link.dataset.nodeType = node.type;
		if ( node.type === 'unit' ) {
			link.dataset.organisationId = ownerId;
		}
		if ( node.type === 'role' ) {
			link.dataset.organisationId = ownerId;
			link.dataset.unitId = node.unitId;
		}
		const currentUrl = new URL( window.location.href );
		const selectedUnit = currentUrl.searchParams.get( 'organisation_unit_id' );
		const selectedRole = currentUrl.searchParams.get( 'role_id' );
		const isCurrent =
			currentUrl.searchParams.get( 'organisation_id' ) === ownerId &&
			( node.type === 'organisation'
				? ! selectedUnit || selectedUnit === '0'
				: selectedUnit === linkNode.unitId ) &&
			( node.type === 'role'
				? selectedRole === node.id
				: ! selectedRole || selectedRole === '0' );
		if ( isCurrent ) {
			link.setAttribute( 'aria-current', 'page' );
		}
		item.append( link );
		if ( node.type !== 'role' ) {
			createBranch(
				{
					type: node.type,
					id: node.id,
					name: node.name,
					organisationId: ownerId,
				},
				item,
			);
		}
		parentList.append( item );
	}

	/**
	 * Expands the selected path without loading unrelated branches.
	 *
	 * @since Unreleased Reveals filters supplied through links or direct URLs.
	 * @param {HTMLUListElement} list Current tree level.
	 * @return {void}
	 */
	function restoreBranches( list ) {
		list.querySelectorAll( ':scope > li > .scouting-oidc-explorer-toggle' ).forEach( ( toggle ) => {
			const key = toggle.dataset.nodeType + ':' + toggle.dataset.nodeId;
			if ( expandedNodes.delete( key ) ) {
				if ( toggle.getAttribute( 'aria-expanded' ) !== 'true' ) {
					toggle.click();
				}
				if ( toggle.dataset.loaded === 'true' ) {
					restoreBranches( document.getElementById( toggle.getAttribute( 'aria-controls' ) ) );
				}
			}
		} );
	}

	/**
	 * Updates the selected marker on already loaded tree links.
	 *
	 * @since Unreleased Keeps the preserved tree in sync with filter navigation.
	 * @return {void}
	 */
	function updateSelection() {
		const current = new URL( window.location.href );
		tree.querySelectorAll( 'a' ).forEach( ( link ) => {
			const target = new URL( link.href );
			const matches = [ 'organisation_id', 'organisation_unit_id', 'role_id' ].every(
				( key ) => ( target.searchParams.get( key ) || '0' ) === ( current.searchParams.get( key ) || '0' ),
			);
			link.removeAttribute( 'aria-current' );
			if ( matches ) {
				link.setAttribute( 'aria-current', 'page' );
			}
		} );
	}

	/**
	 * Refreshes assignments without replacing the open tree or the page.
	 *
	 * @since Unreleased Adds progressive filter navigation and browser history.
	 * @param {string}  url         Filter URL.
	 * @param {boolean} pushHistory Whether to add a browser history entry.
	 * @param {boolean} focusResult Whether pagination should move focus to results.
	 * @return {Promise<void>} Completed filter navigation.
	 */
	async function loadFilter( url, pushHistory, focusResult = false ) {
		if ( filterRequest ) {
			filterRequest.abort();
		}
		const request = new AbortController();
		filterRequest = request;
		const results = document.querySelector( '.scouting-oidc-explorer-results' );
		results.setAttribute( 'aria-busy', 'true' );
		status.textContent = config.labels.loading;
		try {
			const response = await fetch( url, {
				credentials: 'same-origin',
				signal: request.signal,
			} );
			if ( ! response.ok ) {
				throw new Error( config.labels.loadError );
			}
			const page = new DOMParser().parseFromString( await response.text(), 'text/html' );
			const nextResults = page.querySelector( '.scouting-oidc-explorer-results' );
			const nextMeta = page.querySelector( '.scouting-oidc-explorer-tree-panel > .scouting-oidc-explorer-panel-meta' );
			const nextTree = page.getElementById( 'sol-explorer-tree' );
			if ( ! nextResults || ! nextMeta || ! nextTree ) {
				throw new Error( config.labels.loadError );
			}
			const branches = JSON.parse( nextTree.dataset.expandedNodes );
			if ( request.signal.aborted ) {
				return;
			}
			if ( pushHistory ) {
				window.history.pushState( null, '', url );
			}
			results.replaceWith( nextResults );
			document.querySelector( '.scouting-oidc-explorer-tree-panel > .scouting-oidc-explorer-panel-meta' ).replaceWith( nextMeta );
			updateSelection();
			expandedNodes.clear();
			branches.forEach( ( branch ) => expandedNodes.add( branch ) );
			restoreBranches( tree );
			const announcement = document.createElement( 'span' );
			announcement.className = 'screen-reader-text';
			announcement.textContent = nextResults.querySelector( '.scouting-oidc-explorer-panel-meta' ).textContent.trim();
			status.replaceChildren( announcement );
			if ( focusResult ) {
				const heading = nextResults.querySelector( 'h2' );
				heading.tabIndex = -1;
				heading.focus();
			}
		} catch ( error ) {
			if ( error.name !== 'AbortError' ) {
				status.textContent = config.labels.loadError + ' ';
				const retry = document.createElement( 'a' );
				retry.href = url;
				retry.textContent = config.labels.openPage;
				status.append( retry );
			}
		} finally {
			if ( filterRequest === request ) {
				results.removeAttribute( 'aria-busy' );
				filterRequest = null;
			}
		}
	}

	/**
	 * Closes every expanded branch below a collapsed organisation or unit.
	 *
	 * @since Unreleased Closes descendant branches with their parent.
	 * @param {HTMLUListElement} childList Branch contents.
	 * @return {void}
	 */
	function collapseDescendants( childList ) {
		childList.querySelectorAll( '.scouting-oidc-explorer-toggle' ).forEach( ( toggle ) => {
			const descendants = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
			toggle.setAttribute( 'aria-expanded', 'false' );
			toggle.textContent = '+';
			toggle.setAttribute(
				'aria-label',
				config.labels.expand + ' ' + toggle.parentElement.querySelector( 'a' ).textContent,
			);
			if ( descendants ) {
				descendants.hidden = true;
			}
		} );
	}

	/**
	 * Requests one tree level and appends it without replacing already loaded branches.
	 *
	 * @since Unreleased Fetches only the expanded organisation or unit.
	 * @param {HTMLButtonElement} toggle    Branch control.
	 * @param {HTMLUListElement}  childList Branch contents.
	 * @return {Promise<void>} Completed loading operation.
	 */
	async function loadChildren( toggle, childList ) {
		toggle.setAttribute( 'aria-busy', 'true' );
		childList.replaceChildren();
		const loading = document.createElement( 'li' );
		loading.textContent = config.labels.loading;
		childList.append( loading );
		status.textContent = config.labels.loading;
		toggle.dataset.loading = 'true';
		const body = new URLSearchParams( {
			action: 'scouting_oidc_roles_explorer_children',
			_ajax_nonce: config.nonce,
			node_type: toggle.dataset.nodeType,
			node_id: toggle.dataset.nodeId,
		} );

		try {
			const response = await fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body,
			} );
			const result = await response.json();
			if ( ! response.ok || ! result.success || ! Array.isArray( result.data ) ) {
				throw new Error( config.labels.loadError );
			}
			childList.replaceChildren();
			result.data.forEach( ( node ) => {
				if ( node.type === 'role' ) {
					node.unitId = toggle.dataset.nodeId;
				}
				appendNode( node, childList, toggle.dataset.organisationId );
			} );
			if ( result.data.length === 0 ) {
				const empty = document.createElement( 'li' );
				empty.textContent = config.labels.empty;
				childList.append( empty );
			}
			toggle.dataset.loaded = 'true';
			status.textContent = '';
		} catch {
			childList.replaceChildren();
			const failure = document.createElement( 'li' );
			failure.textContent = config.labels.loadError;
			childList.append( failure );
			toggle.setAttribute( 'aria-expanded', 'false' );
			toggle.textContent = '+';
			childList.hidden = true;
			toggle.setAttribute( 'aria-label', config.labels.expand + ' ' + toggle.parentElement.querySelector( 'a' ).textContent );
			status.textContent = config.labels.loadError;
		} finally {
			toggle.removeAttribute( 'aria-busy' );
			delete toggle.dataset.loading;
		}
		if ( toggle.dataset.loaded === 'true' && toggle.getAttribute( 'aria-expanded' ) === 'true' ) {
			restoreBranches( childList );
		}
	}

	tree.addEventListener( 'click', ( event ) => {
		const toggle = event.target.closest( '.scouting-oidc-explorer-toggle' );
		if ( ! toggle || ! tree.contains( toggle ) ) {
			return;
		}
		const childList = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
		if ( ! childList ) {
			return;
		}
		const isExpanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
		toggle.setAttribute( 'aria-expanded', isExpanded ? 'false' : 'true' );
		childList.hidden = isExpanded;
		toggle.textContent = isExpanded ? '+' : '−';
		if ( isExpanded ) {
			collapseDescendants( childList );
		}
		toggle.setAttribute(
			'aria-label',
			( isExpanded ? config.labels.expand : config.labels.collapse ) + ' ' +
				toggle.parentElement.querySelector( 'a' ).textContent,
		);
		if ( ! isExpanded && toggle.dataset.loaded !== 'true' && toggle.dataset.loading !== 'true' ) {
			loadChildren( toggle, childList );
		}
	} );

	document.querySelector( '.scouting-oidc-explorer-layout' ).addEventListener( 'click', ( event ) => {
		const link = event.target.closest( 'a' );
		if ( ! link || event.defaultPrevented || event.button !== 0 ||
			event.ctrlKey || event.metaKey || event.shiftKey || event.altKey ||
			link.target || link.hasAttribute( 'download' ) ) {
			return;
		}
		const isTreeLink = tree.contains( link );
		if ( ! isTreeLink && ! link.closest( '.tablenav-pages' ) ) {
			return;
		}
		event.preventDefault();
		loadFilter( link.href, true, ! isTreeLink );
	} );

	window.addEventListener( 'popstate', () => {
		loadFilter( window.location.href, false );
	} );

	restoreBranches( tree );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', scoutingOidcInitializeRoleExplorer );
} else {
	scoutingOidcInitializeRoleExplorer();
}
