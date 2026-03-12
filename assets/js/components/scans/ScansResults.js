import { BaseComponent } from "../BaseComponent";
import { ShieldTablesScanResultsHandler } from "../tables/ShieldTablesScanResultsHandler";
import { OffCanvasService } from "../ui/OffCanvasService";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { UiContentActivator } from "../ui/UiContentActivator";

export class ScansResults extends BaseComponent {

	init() {
		this.bindModePanelOpenAdjustHandler();
		this.exec();
	}

	run() {
		new ShieldTablesScanResultsHandler( this._base_data.vars.scan_results_tables );

		this.handleResultsTabs();
		this.handleScanResultsDisplayForm();
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

	handleScanResultsDisplayForm() {

		shieldEventsHandler_Main.add_Click( '.offcanvas_form_scans_results_options', ( targetEl ) => {
			OffCanvasService.RenderCanvas( this._base_data.ajax.render_offcanvas ).finally();
		} );

		shieldEventsHandler_Main.add_Submit( '#FormScanResultsDisplayOptions', ( form ) => {

			form.querySelectorAll( 'input[type=checkbox]' ).forEach( ( checkbox ) => {
				if ( !checkbox.checked ) {
					checkbox.value = 'N';
					checkbox.checked = true;
				}
				else {
					checkbox.value = 'Y';
				}
			} );

			( new AjaxService() )
			.send( ObjectOps.Merge(
				this._base_data.ajax.form_scan_results_display_submit,
				{ 'form_data': Object.fromEntries( new FormData( form ) ) }
			) )
			.then( () => OffCanvasService.CloseCanvas() )
			.finally();
		} );
	}
}
