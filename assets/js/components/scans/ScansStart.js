import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { Forms } from "../../util/Forms";
import { ObjectOps } from "../../util/ObjectOps";
import { ScanRunPage } from "./ScanRunPage";

export class ScansStart extends BaseComponent {

	init() {
		shieldEventsHandler_Main.add_Submit( 'form#StartScans', ( targetEl ) => this.startScans( targetEl ) );
		document.addEventListener( 'change', ( evt ) => {
			if ( evt.target instanceof Element && evt.target.matches( '#StartScans input[data-scan-choice]' ) ) {
				ScanRunPage.SyncRunButton();
			}
		} );
		ScanRunPage.SyncRunButton();
	}

	/**
	 * Starting replaces any polling already under way (Run Manual Scan can be opened to choose scans while others
	 * run); the polling only follows the scans again once the start reports them running.
	 */
	startScans( form ) {
		if ( !ScanRunPage.HasChoice( form ) ) {
			return;
		}
		const scansCheck = this._base_data.scansCheck;
		scansCheck.stop();
		ScanRunPage.ShowLocal( 'initiating' );

		( new AjaxService() )
		.send(
			ObjectOps.Merge( this._base_data.ajax.start, { form_params: Forms.Serialize( form ) } ),
			false,
			true
		)
		.then( ( resp ) => {
			if ( !ScanRunPage.HasProgress( resp ) ) {
				ScanRunPage.ShowLocal( 'failed', ScanRunPage.ErrorMessage( resp ) );
				return;
			}
			ScanRunPage.Show( resp.data.progress_html );
			if ( resp.data.progress_state === 'running' ) {
				scansCheck.track( resp.data.scan_ids );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
			ScanRunPage.ShowLocal( 'failed' );
		} );
	}
}
