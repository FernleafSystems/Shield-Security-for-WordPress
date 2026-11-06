import { markCurrentTab, showTabPanel } from "../../util/Tabs";

/**
 * Reports' tabs: charts and trends, the security reports, and the reporting and alerts settings. Every tab
 * is on the page from the start, so each opens in place; the address follows, so a reload or a copied link
 * opens the same tab. The settings open on their first component.
 */
export class ReportsTabs {

	static LANDING = '[data-reports-landing="1"]';

	constructor() {
		shieldEventsHandler_Main.add_Click( `${ReportsTabs.LANDING} [data-reports-tab]`, ( tab ) => {
			this.activate( tab.closest( ReportsTabs.LANDING ), tab.getAttribute( 'data-reports-tab' ) || '' );
		}, true );

		// Once every component is running, since the settings use Configure's.
		const landing = document.querySelector( ReportsTabs.LANDING );
		if ( landing instanceof HTMLElement ) {
			window.setTimeout( () => this.openSettings( landing, String( landing.dataset.reportsCurrentTab || '' ) ) );
		}
	}

	/**
	 * @param {Element|null} landing
	 * @param {string} key
	 */
	activate( landing, key ) {
		if ( !( landing instanceof HTMLElement ) || key === '' ) {
			return;
		}
		landing.dataset.reportsCurrentTab = key;
		const link = markCurrentTab( landing, 'data-reports-tab', key );
		if ( link instanceof HTMLAnchorElement && link.href !== window.location.href ) {
			window.history.replaceState( window.history.state, '', link.href );
		}
		showTabPanel( landing, 'data-reports-tabpanel', key );
		this.openSettings( landing, key );
	}

	/**
	 * The settings show as Configure's component list, opening on its first component.
	 * @param {HTMLElement} landing
	 * @param {string} key
	 */
	openSettings( landing, key ) {
		const panel = landing.querySelector( `[data-reports-tabpanel="${key}"][data-configure-embed="1"]` );
		const diagnosis = panel?.querySelector( '[data-configure-diagnosis="1"]' );
		if ( !( panel instanceof HTMLElement ) || !( diagnosis instanceof HTMLElement ) || panel.dataset.reportsSettingsOpened === '1' ) {
			return;
		}
		panel.dataset.reportsSettingsOpened = '1';
		shieldAppMain?.components?.configure_zone_tools?.ensureOnePanelOpen( diagnosis );
	}
}
