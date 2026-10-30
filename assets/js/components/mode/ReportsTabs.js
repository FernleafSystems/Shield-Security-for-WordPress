/**
 * Reports' tabs: charts and trends, the security reports, and the reporting and alerts settings. Every tab
 * is on the page from the start, so each opens in place; the address follows, so a reload or a copied link
 * opens the same tab. The settings open with both of their panels showing.
 */
export class ReportsTabs {

	static LANDING = '[data-reports-landing="1"]';

	constructor() {
		shieldEventsHandler_Main.add_Click( `${ReportsTabs.LANDING} [data-reports-tab]`, ( tab ) => {
			this.activate( tab.closest( ReportsTabs.LANDING ), tab.getAttribute( 'data-reports-tab' ) || '' );
		}, true );

		const landing = document.querySelector( ReportsTabs.LANDING );
		if ( landing instanceof HTMLElement ) {
			this.openSettings( landing, String( landing.dataset.reportsCurrentTab || '' ) );
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
		landing.querySelectorAll( '[data-reports-tab]' ).forEach( ( link ) => {
			const isCurrent = link.getAttribute( 'data-reports-tab' ) === key;
			link.classList.toggle( 'is-current', isCurrent );
			isCurrent ? link.setAttribute( 'aria-current', 'page' ) : link.removeAttribute( 'aria-current' );
			if ( isCurrent && link instanceof HTMLAnchorElement && link.href !== window.location.href ) {
				window.history.replaceState( window.history.state, '', link.href );
			}
		} );
		landing.querySelectorAll( '[data-reports-tabpanel]' ).forEach( ( panel ) => {
			if ( panel instanceof HTMLElement ) {
				panel.hidden = panel.getAttribute( 'data-reports-tabpanel' ) !== key;
			}
		} );
		this.openSettings( landing, key );
	}

	/**
	 * There are only a couple of settings panels, so they all show rather than wait to be opened.
	 * @param {HTMLElement} landing
	 * @param {string} key
	 */
	openSettings( landing, key ) {
		const panel = landing.querySelector( `[data-reports-tabpanel="${key}"][data-configure-embed="1"]` );
		if ( !( panel instanceof HTMLElement ) || panel.dataset.reportsSettingsOpened === '1' ) {
			return;
		}
		panel.dataset.reportsSettingsOpened = '1';
		panel.querySelectorAll( '[data-shield-expand-trigger="1"][aria-expanded="false"]' ).forEach(
			( trigger ) => trigger instanceof HTMLElement && trigger.click()
		);
	}
}
