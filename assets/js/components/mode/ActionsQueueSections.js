import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

/**
 * The Actions Queue landing lists each lane as a collapsible section, or as tabs. The choice of
 * sections or tabs is stored per user; the landing may be re-rendered after actions, so all state
 * lives in the markup and handlers are delegated.
 */
export class ActionsQueueSections extends BaseAutoExecComponent {

	static ROOT_SELECTOR = '[data-actions-queue-sections="1"]';

	run() {
		shieldEventsHandler_Main.add_Click( '[data-actions-queue-section-toggle="1"]', ( head ) => {
			const root = head.closest( ActionsQueueSections.ROOT_SELECTOR );
			if ( root?.dataset.view === 'tabs' ) {
				return;
			}
			const body = document.getElementById( head.getAttribute( 'aria-controls' ) || '' );
			const isOpen = head.getAttribute( 'aria-expanded' ) === 'true';
			head.setAttribute( 'aria-expanded', String( !isOpen ) );
			body && ( body.hidden = isOpen );
		} );

		// A folded box opens and closes from its heading.
		shieldEventsHandler_Main.add_Click( '[data-actions-queue-box-toggle]', ( toggle ) => {
			const body = document.getElementById( toggle.getAttribute( 'aria-controls' ) || '' );
			const isOpen = toggle.getAttribute( 'aria-expanded' ) === 'true';
			toggle.setAttribute( 'aria-expanded', String( !isOpen ) );
			body && ( body.hidden = isOpen );
		} );

		shieldEventsHandler_Main.add_Click( '[data-actions-queue-tab]', ( tab ) => {
			this.selectTab( tab.closest( ActionsQueueSections.ROOT_SELECTOR ), tab.dataset.actionsQueueTab || '' );
		} );

		shieldEventsHandler_Main.add_Keydown( '[data-actions-queue-tab]', ( tab, evt ) => {
			if ( evt.key !== 'ArrowRight' && evt.key !== 'ArrowLeft' ) {
				return;
			}
			const tabs = [ ...tab.parentElement.querySelectorAll( '[data-actions-queue-tab]' ) ];
			const next = tabs[ ( tabs.indexOf( tab ) + ( evt.key === 'ArrowRight' ? 1 : tabs.length - 1 ) ) % tabs.length ];
			this.selectTab( tab.closest( ActionsQueueSections.ROOT_SELECTOR ), next.dataset.actionsQueueTab || '' );
			next.focus();
		}, false );

		shieldEventsHandler_Main.add_Click( '[data-actions-queue-view]', ( button ) => {
			// The choice sits on the breadcrumb line, outside the sections it switches.
			const root = button.closest( ActionsQueueSections.ROOT_SELECTOR ) || document.querySelector( ActionsQueueSections.ROOT_SELECTOR );
			this.setView( root, button.dataset.actionsQueueView || 'sections' );
		} );

		// A link to one problem, such as from the admin bar, names its group: it's opened and shown once it's there.
		this.focusGroupKey = new URLSearchParams( window.location.search ).get( 'aq_focus' ) || '';

		// The landing is re-rendered after actions, bringing its own choice each time; it always moves up.
		this.placeViewChoice();
		this.focusLinkedGroup();
		new MutationObserver( () => {
			this.placeViewChoice();
			this.focusLinkedGroup();
		} ).observe( document.body, { childList: true, subtree: true } );
	}

	/**
	 * Opens the section (or tab) and folded box holding the linked group, scrolls to it and highlights it briefly.
	 */
	focusLinkedGroup() {
		if ( this.focusGroupKey === '' ) {
			return;
		}
		const group = document.querySelector(
			`${ActionsQueueSections.ROOT_SELECTOR} [data-actions-queue-group-key="${CSS.escape( this.focusGroupKey )}"]`
		);
		if ( !( group instanceof HTMLElement ) ) {
			return;
		}
		this.focusGroupKey = '';

		const root = group.closest( ActionsQueueSections.ROOT_SELECTOR );
		const section = group.closest( '[data-actions-queue-section-key]' );
		if ( root?.dataset.view === 'tabs' ) {
			this.selectTab( root, section?.dataset.actionsQueueSectionKey || '' );
		}
		else {
			const head = section?.querySelector( '[data-actions-queue-section-toggle="1"]' );
			head?.getAttribute( 'aria-expanded' ) === 'false' && head.click();
		}
		const boxToggle = group.querySelector( ':scope [data-actions-queue-box-toggle][aria-expanded="false"]' )
			|| group.closest( '.actions-queue-row-group' )?.querySelector( '[data-actions-queue-box-toggle][aria-expanded="false"]' );
		boxToggle instanceof HTMLElement && boxToggle.click();

		group.scrollIntoView( { block: 'center', behavior: window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ? 'auto' : 'smooth' } );
		group.classList.add( 'is-linked' );
		setTimeout( () => group.classList.remove( 'is-linked' ), 4000 );
	}

	/**
	 * Moves the Sections / Tabs choice up to the end of the breadcrumb line, replacing any moved there before.
	 */
	placeViewChoice() {
		const view = document.querySelector( `${ActionsQueueSections.ROOT_SELECTOR} .actions-queue-sections__view` );
		const nav = document.querySelector( '[data-operator-step-tabs="1"]' );
		if ( view === null || nav === null ) {
			return;
		}
		nav.querySelectorAll( ':scope > .actions-queue-sections__view' ).forEach( ( old ) => old.remove() );
		nav.insertBefore( view, nav.querySelector( ':scope > .operator-step-tabs__actions' ) );
	}

	/**
	 * @param {HTMLElement|null} root
	 * @param {string} key
	 */
	selectTab( root, key ) {
		if ( !( root instanceof HTMLElement ) ) {
			return;
		}
		root.querySelectorAll( '[data-actions-queue-tab]' ).forEach( ( tab ) => {
			const isSelected = tab.dataset.actionsQueueTab === key;
			tab.setAttribute( 'aria-selected', String( isSelected ) );
			tab.tabIndex = isSelected ? 0 : -1;
		} );
		root.querySelectorAll( '[data-actions-queue-section-key]' ).forEach( ( section ) => {
			section.dataset.tabSelected = section.dataset.actionsQueueSectionKey === key ? '1' : '0';
		} );
	}

	/**
	 * @param {HTMLElement|null} root
	 * @param {string} view
	 */
	setView( root, view ) {
		if ( !( root instanceof HTMLElement ) || ![ 'sections', 'tabs' ].includes( view ) ) {
			return;
		}
		root.dataset.view = view;
		root.classList.toggle( 'actions-queue-sections--tabs', view === 'tabs' );
		root.classList.toggle( 'actions-queue-sections--sections', view === 'sections' );
		document.querySelectorAll( '[data-actions-queue-view]' ).forEach(
			( button ) => button.setAttribute( 'aria-pressed', String( button.dataset.actionsQueueView === view ) )
		);
		// Tabs always show their section in full; sections keep whichever were open or collapsed.
		root.querySelectorAll( '[data-actions-queue-section-toggle="1"]' ).forEach( ( head ) => {
			const body = document.getElementById( head.getAttribute( 'aria-controls' ) || '' );
			body && ( body.hidden = view === 'sections' && head.getAttribute( 'aria-expanded' ) !== 'true' );
		} );
		if ( view === 'tabs' ) {
			const selected = root.querySelector( '[data-actions-queue-tab][aria-selected="true"]' );
			this.selectTab( root, selected?.dataset.actionsQueueTab || '' );
		}

		try {
			const action = JSON.parse( root.dataset.setViewAction || '{}' );
			if ( !ObjectOps.IsEmpty( action ) ) {
				( new AjaxService() )
				.bg( ObjectOps.Merge( action, { pref_key: 'actions_queue_view', pref_value: view } ) )
				.catch( () => null );
			}
		}
		catch {
			return;
		}
	}
}
