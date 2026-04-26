import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { Forms } from "../../util/Forms";
import { ObjectOps } from "../../util/ObjectOps";
import { ScansCheck } from "./ScansCheck";
import { ScanProgressModal } from "./ScanProgressModal";

export class ScansStart extends BaseComponent {

	init() {
		shieldEventsHandler_Main.add_Submit( 'form#StartScans', ( targetEl ) => this.startScans( targetEl ) );
	}

	startScans( form ) {
		ScanProgressModal.ShowInitiating( this._base_data.strings || {} );

		( new AjaxService() )
		.send(
			ObjectOps.Merge( this._base_data.ajax.start, { form_params: Forms.Serialize( form ) } ),
			false,
			true
		)
		.then( ( resp ) => {
			if ( !ScanProgressModal.HasModalResponse( resp ) ) {
				ScanProgressModal.ShowError( this._base_data.strings || {}, ScanProgressModal.ExtractErrorMessage( resp ) );
				return;
			}

			ScanProgressModal.ShowHtml( resp.data.modal_html );

			if ( resp.data.scans_running === true ) {
				setTimeout( () => ( new ScansCheck( {
					...this._base_data,
					started_scan_ids: resp.data.scan_ids || []
				} ) ).check(), 1000 );
			}
		} )
		.catch( ( error ) => {
			console.log( error );
			ScanProgressModal.ShowError( this._base_data.strings || {} );
		} )
		.finally();
	};
}
