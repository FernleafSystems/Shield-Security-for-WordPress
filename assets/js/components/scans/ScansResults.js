import { BaseComponent } from "../BaseComponent";
import { ShieldTablesScanResultsHandler } from "../tables/ShieldTablesScanResultsHandler";
import { UiContentActivator } from "../ui/UiContentActivator";

export class ScansResults extends BaseComponent {

	init() {
		this.bindModePanelOpenAdjustHandler();
		this.exec();
	}

	run() {
		new ShieldTablesScanResultsHandler( this._base_data.vars.scan_results_tables );

		this.handleResultsTabs();
		this.activateCurrentResultsPane();
	}

	handleResultsTabs() {
		shieldEventsHandler_Main.addHandler( 'shown.bs.tab', '#ScanResultsTabsNav a.nav-link[data-bs-toggle="tab"]', ( targetEl ) => {
			this.handleScanResultsTabShown( targetEl );
		} );
	}

	bindModePanelOpenAdjustHandler() {
		document.addEventListener( 'shield:mode-panel-opened', ( evt ) => {
			const panel = evt.target?.querySelector?.( '[data-mode-panel="1"].is-open' ) || null;
			if ( panel !== null && panel.querySelector( '#ScanResultsTabs' ) !== null ) {
				UiContentActivator.activateCurrentSubtree( panel );
			}
		} );
	}

	handleScanResultsTabShown( targetEl ) {
		if ( targetEl === null ) {
			return;
		}

		const paneSelector = targetEl.dataset.bsTarget || targetEl.getAttribute( 'href' ) || '';
		const paneEl = paneSelector.startsWith( '#' ) ? document.querySelector( paneSelector ) : null;
		if ( paneEl !== null ) {
			UiContentActivator.activateCurrentSubtree( paneEl );
		}
	}

	activateCurrentResultsPane() {
		const activePane = document.querySelector( '#ScanResultsTabs .tab-pane.active, #ScanResultsTabs .tab-pane.show' );
		if ( activePane !== null ) {
			UiContentActivator.activateCurrentSubtree( activePane );
		}
	}
}
