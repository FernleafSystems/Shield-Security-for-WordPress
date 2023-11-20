import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { Forms } from "../../util/Forms";
import { ObjectOps } from "../../util/ObjectOps";

export class MalaiFileScanQuery extends BaseComponent {

	init() {
		shieldEventsHandler_Main.add_Submit( 'form#FileScanMalaiQuery', ( targetEl ) => {

			let ready = true;

			targetEl.querySelectorAll( 'input[type=checkbox]' ).forEach(
				( checkbox ) => {
					ready = ready && checkbox.checked;
				}
			);

			if ( ready ) {
				( new AjaxService() )
				.send( ObjectOps.Merge( this._base_data.ajax.malai_file_query, Forms.Serialize( targetEl ) ) )
				.finally();
			}
			else {
				alert( 'Please check the box to agree.' );
			}
		} );
	}
}