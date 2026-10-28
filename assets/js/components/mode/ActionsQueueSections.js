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
			this.setView( button.closest( ActionsQueueSections.ROOT_SELECTOR ), button.dataset.actionsQueueView || 'sections' );
		} );
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
		root.querySelectorAll( '[data-actions-queue-view]' ).forEach(
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
