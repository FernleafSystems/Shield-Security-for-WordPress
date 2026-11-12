import { Collapse } from 'bootstrap';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { ConfigureTabs } from "./ConfigureTabs";

/**
 * A Configure goal's components are a list beside the one open panel. Its title line carries an in-place
 * filter and a "Needs attention" view.
 */
export class ConfigureZoneTools extends BaseAutoExecComponent {

	static ROOT_SELECTOR = '[data-configure-diagnosis="1"]';
	static ITEM_SELECTOR = '.shield-detail-item--configure';

	canRun() {
		return !!this._base_data?.ajax;
	}

	run() {
		document.addEventListener( 'input', ( evt ) => {
			const input = evt.target;
			if ( input instanceof HTMLInputElement && input.matches( '[data-configure-zone-filter="1"]' ) ) {
				this.applyFilters( input.closest( ConfigureZoneTools.ROOT_SELECTOR ) );
			}
		} );

		shieldEventsHandler_Main.add_Click( '[data-configure-zone-show]', ( button ) => {
			const root = button.closest( ConfigureZoneTools.ROOT_SELECTOR );
			root?.querySelectorAll( '[data-configure-zone-show]' ).forEach(
				( chip ) => chip.setAttribute( 'aria-pressed', String( chip === button ) )
			);
			this.applyFilters( root );
		} );

		// A goal's link that changes something (turning Security Admin off) asks first.
		shieldEventsHandler_Main.add_Click( '.configure-zone-tools__link[data-confirm-message]', async ( link, evt ) => {
			evt.preventDefault();
			const dialog = shieldServices.dialog();
			const confirmed = await dialog.confirm( {
				message: link.dataset.confirmMessage,
				confirmLabel: dialog.resolveConfirmLabel( link ),
				danger: true,
				launcher: link,
			} );
			if ( confirmed ) {
				window.location.href = link.href;
			}
		}, true );

		// In the component list only one panel is open: opening one closes the others, and the open one stays open.
		document.addEventListener( 'show.bs.collapse', ( evt ) => {
			const root = this.listRootFor( evt.target );
			if ( root === null ) {
				return;
			}
			root.querySelectorAll( '.shield-detail-expansion.show, .shield-detail-expansion.collapsing' ).forEach( ( open ) => {
				if ( open !== evt.target ) {
					this.closePanel( open );
				}
			} );
		} );
		// The open component stays open: in the list a panel is replaced, never closed.
		document.addEventListener( 'hide.bs.collapse', ( evt ) => {
			if ( this.listRootFor( evt.target ) !== null ) {
				evt.preventDefault();
			}
		} );

		// Settings arrive as their topics load; a narrowed view applies to them as they do.
		document.addEventListener( 'shield:configure-panel-loaded', ( evt ) => {
			const root = evt.target instanceof Element ? evt.target.closest( ConfigureZoneTools.ROOT_SELECTOR ) : null;
			if ( root instanceof HTMLElement && ( root.hasAttribute( 'data-filtering' ) || root.hasAttribute( 'data-attention-only' ) ) ) {
				this.applyFilters( root );
			}
		} );

		// Moving to another goal keeps the filter, so the goals it fades can be visited without retyping it.
		new MutationObserver( () => {
			document.querySelectorAll( `${ConfigureZoneTools.ROOT_SELECTOR}:not([data-filter-restored])` ).forEach( ( root ) => {
				root.dataset.filterRestored = '1';
				this.restoreFilter( root );
			} );
		} ).observe( document.body, { childList: true, subtree: true } );

		// A goal renders asynchronously; open a panel when one appears.
		new MutationObserver( () => {
			document.querySelectorAll( `${ConfigureZoneTools.ROOT_SELECTOR}:not([data-configure-list-ready])` )
			.forEach( ( root ) => {
				root.dataset.configureListReady = '1';
				this.ensureOnePanelOpen( root );
			} );
		} ).observe( document.body, { childList: true, subtree: true } );
	}

	/**
	 * @param {EventTarget|null} target
	 * @return {HTMLElement|null}
	 */
	listRootFor( target ) {
		const root = target instanceof Element ? target.closest( ConfigureZoneTools.ROOT_SELECTOR ) : null;
		return root instanceof HTMLElement && target.matches( '[data-shield-expand-body="1"]' )
			? root
			: null;
	}

	/**
	 * Keeps the first open panel (or opens the first component) and closes the rest.
	 * @param {HTMLElement} root
	 */
	ensureOnePanelOpen( root ) {
		const panels = [ ...root.querySelectorAll( `${ConfigureZoneTools.ITEM_SELECTOR}:not([hidden]) [data-shield-expand-body="1"]` ) ];
		if ( panels.length === 0 ) {
			return;
		}
		// A panel still opening (a search jump's target) counts as the open one.
		const keep = panels.find( ( panel ) => panel.classList.contains( 'show' ) || panel.classList.contains( 'collapsing' ) )
			|| panels[ 0 ];
		panels.forEach( ( panel ) => {
			if ( panel !== keep && ( panel.classList.contains( 'show' ) || panel.classList.contains( 'collapsing' ) ) ) {
				this.closePanel( panel );
			}
		} );
		if ( !keep.classList.contains( 'collapsing' ) ) {
			Collapse.getOrCreateInstance( keep, { toggle: false } ).show();
		}
	}

	/**
	 * Closes a component's panel at once. Bootstrap ignores a close while a panel is still opening,
	 * which happens when a search jump opens one component just as the list opens the first, and
	 * would leave two panels open on top of each other.
	 * @param {HTMLElement} panel
	 */
	closePanel( panel ) {
		Collapse.getInstance( panel )?.dispose();
		panel.classList.remove( 'show', 'collapsing' );
		panel.classList.add( 'collapse' );
		panel.style.height = '';
		// Lets the row update its own open state, as it would after an animated close.
		panel.dispatchEvent( new Event( 'hidden.bs.collapse', { bubbles: true } ) );
	}

	/**
	 * @param {HTMLElement} root
	 */
	restoreFilter( root ) {
		const state = this.filterState || { query: '', attentionOnly: false };
		const attention = root.querySelector( '[data-configure-zone-show="attention"]' );
		if ( state.query.length === 0 && !( state.attentionOnly && attention !== null ) ) {
			return;
		}
		const input = root.querySelector( '[data-configure-zone-filter="1"]' );
		input instanceof HTMLInputElement && ( input.value = state.query );
		if ( state.attentionOnly && attention !== null ) {
			root.querySelectorAll( '[data-configure-zone-show]' ).forEach(
				( chip ) => chip.setAttribute( 'aria-pressed', String( chip === attention ) )
			);
		}
		this.applyFilters( root );
	}

	/**
	 * The filter matches each setting's name and summary, so it loads every topic's settings first and
	 * is applied again as each arrives. A topic whose own name matches keeps all its settings; otherwise
	 * only the matching settings show, and groups left empty are hidden (styled in config-panels).
	 * @param {HTMLElement|null} root
	 */
	applyFilters( root ) {
		if ( !( root instanceof HTMLElement ) ) {
			return;
		}

		const query = String( root.querySelector( '[data-configure-zone-filter="1"]' )?.value || '' ).trim().toLowerCase();
		const attentionOnly = root.querySelector( '[data-configure-zone-show="attention"][aria-pressed="true"]' ) !== null;
		// Inside panels, "Needs attention" also leaves only the settings that need it (styled in config-panels).
		root.toggleAttribute( 'data-attention-only', attentionOnly );
		root.toggleAttribute( 'data-filtering', query.length > 0 );
		this.filterState = { query, attentionOnly };
		ConfigureTabs.fade( root, query, attentionOnly );
		if ( query.length > 0 ) {
			shieldAppMain?.components?.configure_expand_loader?.preloadExpansionForms();
		}
		let visibleCount = 0;

		root.querySelectorAll( ConfigureZoneTools.ITEM_SELECTOR ).forEach( ( item ) => {
			const row = item.querySelector( '.shield-detail-row' );
			const needsAttention = row?.matches( '.shield-detail-row--warning, .shield-detail-row--critical' ) ?? false;
			const titleMatches = query.length > 0
				&& String( row?.querySelector( '.shield-detail-row__title' )?.textContent || '' ).toLowerCase().includes( query );

			const options = [ ...item.querySelectorAll( '.shield-option-item' ) ];
			let optionMatches = 0;
			options.forEach( ( option ) => {
				const text = [ '.option_label_name', '.options-summary' ]
				.map( ( selector ) => option.querySelector( selector )?.textContent || '' )
				.join( ' ' )
				.toLowerCase();
				const matches = query.length === 0 || titleMatches || text.includes( query );
				option.classList.toggle( 'is-filtered-out', !matches );
				if ( matches && query.length > 0 && !titleMatches ) {
					optionMatches++;
				}
			} );

			// Until its settings arrive, a topic stays in view if its own description matches.
			const textMatches = query.length === 0 || titleMatches || optionMatches > 0
				|| ( options.length === 0 && String( row?.textContent || '' ).toLowerCase().includes( query ) );
			const visible = textMatches && ( !attentionOnly || needsAttention );
			item.hidden = !visible;
			visibleCount += visible ? 1 : 0;
		} );

		const empty = root.querySelector( '[data-configure-zone-empty="1"]' );
		if ( empty instanceof HTMLElement ) {
			empty.hidden = visibleCount > 0;
		}
		this.ensureOnePanelOpen( root );
	}
}
