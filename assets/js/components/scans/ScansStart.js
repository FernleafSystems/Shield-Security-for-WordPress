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

	startScans( form ) {
		if ( !ScanRunPage.HasChoice( form ) ) {
			return;
		}
		ScanRunPage.ShowInitiating( this._base_data.strings );

		( new AjaxService() )
		.send(
			ObjectOps.Merge( this._base_data.ajax.start, { form_params: Forms.Serialize( form ) } ),
			false,
			true
		)
		.then( ( resp ) => {
			if ( !ScanRunPage.HasProgressResponse( resp ) ) {
				ScanRunPage.ShowError( this._base_data.strings, ScanRunPage.ErrorMessage( resp ) );
				return;
			}

			ScanRunPage.Show( resp.data.modal_html );

			if ( resp.data.scans_running === true ) {
				this._base_data.scansCheck.replaceTrackedScans( resp.data.scan_ids );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
			ScanRunPage.ShowError( this._base_data.strings );
		} );
	}
}
