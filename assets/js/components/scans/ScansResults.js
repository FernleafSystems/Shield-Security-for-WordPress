import $ from 'jquery';
import { BaseComponent } from "../BaseComponent";
import { ScansActionAbandonedPlugins } from "./ScansActionAbandonedPlugins";
import { ShieldTablesScanResultsHandler } from "../tables/ShieldTablesScanResultsHandler";
import { OffCanvasService } from "../ui/OffCanvasService";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { ShieldTableScanResults } from "../tables/ShieldTableScanResults";

export class ScansResults extends BaseComponent {

	init() {
		$( '.nav-vertical a[data-bs-toggle="tab"]' ).on( 'shown.bs.tab', ( evt ) => {
			window.scrollTo( { top: 0, behavior: 'smooth' } )
		} );
		this.exec();
	}

	run() {
		new ScansActionAbandonedPlugins( this._base_data );
		new ShieldTablesScanResultsHandler( this._base_data.vars.scan_results_tables );

		this.handlePluginThemeTables();
		this.handleScanResultsDisplayForm();
	}

	handlePluginThemeTables() {
		this.loadedAssets = [];

		shieldEventsHandler_Main.addHandler( 'shown.bs.tab', '#ScanResultsTabsNav a.nav-link[data-bs-toggle="tab"]', ( targetEl, evt ) => {
			if ( targetEl.id === 'h-tabs-plugins-tab' || targetEl.id === 'h-tabs-themes-tab' ) {
				const tabPane = document.getElementById( targetEl.dataset[ 'bsTarget' ].slice( 1 ) ) || false;
				const selectedSubTab = tabPane.querySelectorAll( '.scan-results-section ul.nav-tabs a.active' );
				if ( selectedSubTab.length === 0 ) {
					let first = tabPane.querySelector( '.scan-results-section ul.nav-tabs a' );
					if ( first ) {
						first.click();
					}
				}
			}
		} );

		shieldEventsHandler_Main.add_Click( '.scan-results-section ul.nav-tabs a', ( targetEl ) => {
			this.handleDisplayScanResultsForAsset( targetEl );
		} );
		shieldEventsHandler_Main.add_DblClick( '.scan-results-section ul.nav-tabs a', ( targetEl ) => {
			this.handleDisplayScanResultsForAsset( targetEl, true );
		} );
	}

	handleDisplayScanResultsForAsset( targetEl, forceReload = false ) {
		const tabContent = document.getElementById( targetEl.getAttribute( 'href' ).slice( 1 ) ) || false;
		if ( tabContent ) {
			const key = 'asset-' + targetEl.dataset.type + targetEl.dataset.unique_id;
			if ( forceReload || !this.loadedAssets.includes( key ) ) {
				tabContent.innerHTML = '';

				( new AjaxService() )
				.send( ObjectOps.Merge(
					this._base_data.ajax.render_asset_results_panel,
					targetEl.dataset
				) )
				.then( ( resp ) => {
					this.loadedAssets.push( key );
					tabContent.innerHTML = resp.data.html;

					let assetTableData = ObjectOps.ObjClone( this._base_data.vars.scan_results_tables[ 'plugin_theme' ] );
					const tableEl = tabContent.querySelector( assetTableData.vars.table_selector );
					if ( tableEl && tableEl.id ) {
						assetTableData.vars.table_selector = '#' + tableEl.id;
						assetTableData.ajax.table_action.type = tableEl.dataset.type;
						assetTableData.ajax.table_action.file = tableEl.dataset.file;
						new ShieldTableScanResults( assetTableData );
					}
				} )
				.finally();
			}
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