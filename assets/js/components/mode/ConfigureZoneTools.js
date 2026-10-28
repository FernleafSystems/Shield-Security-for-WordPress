import { Collapse } from 'bootstrap';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

/**
 * Tools above a Configure zone's components: an in-place filter, a "Needs attention" view, and
 * a choice between expanding components in place and a component list beside one open panel.
 * The layout choice is stored per user.
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

		shieldEventsHandler_Main.add_Click( '[data-configure-zone-layout]', ( button ) => {
			this.setLayout( button.closest( ConfigureZoneTools.ROOT_SELECTOR ), button.dataset.configureZoneLayout || 'expand', true );
		} );

		// In the component list only one panel is open: opening one closes the others, and the open one stays open.
		document.addEventListener( 'show.bs.collapse', ( evt ) => {
			const root = this.listRootFor( evt.target );
			if ( root === null ) {
				return;
			}
			root.dataset.configureLayoutSwitching = '1';
			root.querySelectorAll( '.shield-detail-expansion.show' ).forEach( ( open ) => {
				if ( open !== evt.target ) {
					Collapse.getOrCreateInstance( open, { toggle: false } ).hide();
				}
			} );
			delete root.dataset.configureLayoutSwitching;
		} );
		document.addEventListener( 'hide.bs.collapse', ( evt ) => {
			const root = this.listRootFor( evt.target );
			if ( root !== null && !root.dataset.configureLayoutSwitching ) {
				evt.preventDefault();
			}
		} );

		// A zone renders asynchronously; open a panel when a list-layout zone appears.
		new MutationObserver( () => {
			document.querySelectorAll( `${ConfigureZoneTools.ROOT_SELECTOR}[data-configure-layout="list"]:not([data-configure-layout-ready])` )
			.forEach( ( root ) => {
				root.dataset.configureLayoutReady = '1';
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
		return root instanceof HTMLElement && root.dataset.configureLayout === 'list'
			   && target.matches( '[data-shield-expand-body="1"]' )
			? root
			: null;
	}

	/**
	 * @param {HTMLElement|null} root
	 * @param {string} layout
	 * @param {boolean} persist
	 */
	setLayout( root, layout, persist ) {
		if ( !( root instanceof HTMLElement ) || ![ 'expand', 'list' ].includes( layout ) ) {
			return;
		}

		root.dataset.configureLayout = layout;
		root.classList.toggle( 'configure-diagnosis--layout-list', layout === 'list' );
		root.classList.toggle( 'configure-diagnosis--layout-expand', layout === 'expand' );
		root.querySelectorAll( '[data-configure-zone-layout]' ).forEach(
			( button ) => button.setAttribute( 'aria-pressed', String( button.dataset.configureZoneLayout === layout ) )
		);
		if ( layout === 'list' ) {
			this.ensureOnePanelOpen( root );
		}

		if ( persist && this._base_data.ajax.set_ui_preference ) {
			( new AjaxService() )
			.bg( ObjectOps.Merge( this._base_data.ajax.set_ui_preference, {
				pref_key: 'configure_zone_layout',
				pref_value: layout,
			} ) )
			.catch( () => null );
		}
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
		const keep = panels.find( ( panel ) => panel.classList.contains( 'show' ) ) || panels[ 0 ];
		root.dataset.configureLayoutSwitching = '1';
		panels.forEach( ( panel ) => {
			if ( panel !== keep && panel.classList.contains( 'show' ) ) {
				Collapse.getOrCreateInstance( panel, { toggle: false } ).hide();
			}
		} );
		delete root.dataset.configureLayoutSwitching;
		Collapse.getOrCreateInstance( keep, { toggle: false } ).show();
	}

	/**
	 * @param {HTMLElement|null} root
	 */
	applyFilters( root ) {
		if ( !( root instanceof HTMLElement ) ) {
			return;
		}

		const query = String( root.querySelector( '[data-configure-zone-filter="1"]' )?.value || '' ).trim().toLowerCase();
		const attentionOnly = root.querySelector( '[data-configure-zone-show="attention"][aria-pressed="true"]' ) !== null;
		let visibleCount = 0;

		root.querySelectorAll( ConfigureZoneTools.ITEM_SELECTOR ).forEach( ( item ) => {
			const row = item.querySelector( '.shield-detail-row' );
			const needsAttention = row?.matches( '.shield-detail-row--warning, .shield-detail-row--critical' ) ?? false;
			const rowMatches = query.length === 0 || String( row?.textContent || '' ).toLowerCase().includes( query );

			let optionMatches = 0;
			item.querySelectorAll( '.shield-option-item' ).forEach( ( option ) => {
				const text = [ '.option_label_name', '.options-summary' ]
				.map( ( selector ) => option.querySelector( selector )?.textContent || '' )
				.join( ' ' )
				.toLowerCase();
				const matches = query.length > 0 && text.includes( query );
				// Inside a component that matched only by its settings, show just the matching settings.
				option.classList.toggle( 'is-filtered-out', query.length > 0 && !rowMatches && !matches );
				optionMatches += matches ? 1 : 0;
			} );

			const visible = ( rowMatches || optionMatches > 0 ) && ( !attentionOnly || needsAttention );
			item.hidden = !visible;
			visibleCount += visible ? 1 : 0;

			if ( visible && optionMatches > 0 && !rowMatches && root.dataset.configureLayout !== 'list' ) {
				const panel = item.querySelector( '[data-shield-expand-body="1"]' );
				panel && Collapse.getOrCreateInstance( panel, { toggle: false } ).show();
			}
		} );

		const empty = root.querySelector( '[data-configure-zone-empty="1"]' );
		if ( empty instanceof HTMLElement ) {
			empty.hidden = visibleCount > 0;
		}
		if ( root.dataset.configureLayout === 'list' ) {
			this.ensureOnePanelOpen( root );
		}
	}
}
