import $ from 'jquery';
import { BaseComponent } from "../BaseComponent";
import { ScansActionAbandonedPlugins } from "./ScansActionAbandonedPlugins";
import { ShieldTablesScanResultsHandler } from "../tables/ShieldTablesScanResultsHandler";
import { OffCanvasService } from "../ui/OffCanvasService";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";

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

		this.handleScanResultsDisplayForm();
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