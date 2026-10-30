import { getActiveLayerIndex, getLayersForShell } from "./DrillDownShared";

/**
 * Configure's tabs: Search, then one tab for each goal. A goal tab carries what opens its goal, so the
 * landing's own handler loads it in place; this keeps the current tab in step with what's open, shows the
 * search only on the Search tab, and fades the goals that have nothing for the current filter.
 */
export class ConfigureTabs {

	static LANDING = '[data-configure-landing="1"]';

	constructor() {
		shieldEventsHandler_Main.add_Click( `${ConfigureTabs.LANDING} [data-configure-tab]`, ( tab ) => {
			const landing = tab.closest( ConfigureTabs.LANDING );
			if ( !( landing instanceof HTMLElement ) ) {
				return;
			}
			const key = tab.getAttribute( 'data-configure-tab' ) || 'search';
			if ( key === 'search' ) {
				const shell = landing.querySelector( '[data-drill-shell="1"]' );
				if ( shell instanceof HTMLElement && getActiveLayerIndex( getLayersForShell( shell ) ) > 0 ) {
					shieldAppMain?.components?.drill_down?.drillTo( shell, 0 );
				}
				const input = landing.querySelector( '[data-configure-search-input="1"]' );
				input instanceof HTMLElement && input.focus();
			}
			this.showTab( landing, key );
		}, true );

		// Search results and the goals needing attention open a goal too; the tabs follow.
		[ 'shield:drill-to', 'shield:drill-back', 'shield:drill-header-updated' ].forEach( ( name ) => {
			document.addEventListener( name, ( evt ) => {
				const landing = evt.target instanceof Element ? evt.target.closest( ConfigureTabs.LANDING ) : null;
				if ( landing instanceof HTMLElement ) {
					this.sync( landing );
				}
			} );
		} );
	}

	/**
	 * @param {HTMLElement} landing
	 */
	sync( landing ) {
		const shell = landing.querySelector( '[data-drill-shell="1"]' );
		const isGoalOpen = shell instanceof HTMLElement && getActiveLayerIndex( getLayersForShell( shell ) ) > 0;
		const goal = shieldAppMain?.components?.configure_landing?.selectedZone?.key || '';
		this.showTab( landing, isGoalOpen && goal.length > 0 ? goal : 'search' );
	}

	/**
	 * @param {HTMLElement} landing
	 * @param {string} key
	 */
	showTab( landing, key ) {
		landing.dataset.configureCurrentTab = key;
		landing.querySelectorAll( '[data-configure-tab]' ).forEach( ( tab ) => {
			const isCurrent = tab.getAttribute( 'data-configure-tab' ) === key;
			tab.classList.toggle( 'is-current', isCurrent );
			isCurrent ? tab.setAttribute( 'aria-current', 'page' ) : tab.removeAttribute( 'aria-current' );
		} );
		const search = landing.querySelector( '[data-configure-tabpanel="search"]' );
		search instanceof HTMLElement && ( search.hidden = key !== 'search' );
		// The search tab has its own search, so a goal's filter doesn't fade anything there.
		if ( key === 'search' ) {
			ConfigureTabs.fade( landing, '', false );
		}
	}

	/**
	 * Fades the goals with nothing for the filter: no setting with the typed words, or nothing that needs
	 * attention. They stay usable.
	 * @param {Element|null} within
	 * @param {string} query
	 * @param {boolean} attentionOnly
	 */
	static fade( within, query, attentionOnly ) {
		const landing = within instanceof Element ? within.closest( ConfigureTabs.LANDING ) : null;
		if ( !( landing instanceof HTMLElement ) ) {
			return;
		}
		landing.querySelectorAll( '[data-configure-tab][data-configure-tab-words]' ).forEach( ( tab ) => {
			const words = tab.getAttribute( 'data-configure-tab-words' ) || '';
			const faded = ( query.length > 0 && !words.includes( query ) )
				|| ( attentionOnly && tab.getAttribute( 'data-configure-tab-attention' ) !== '1' );
			tab.classList.toggle( 'is-faded', faded );
		} );
	}
}
