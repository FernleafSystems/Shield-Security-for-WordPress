import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { Forms } from "../../util/Forms";

export class Blockdown extends BaseAutoExecComponent {

	run() {
		this.handleForms();
	}

	handleForms() {
		shieldEventsHandler_Main.add_Submit( '#FormBlockdown', ( form ) => {
			( new AjaxService() )
			.send(
				ObjectOps.Merge( this._base_data.ajax.blockdown_form_submit, { 'form_data': Forms.Serialize( form ) } )
			)
			.finally();
		} );
		shieldEventsHandler_Main.add_Submit( '#FormBlockdownDisable', ( form ) => {
			( new AjaxService() )
			.send(
				ObjectOps.Merge( this._base_data.ajax.blockdown_disable_form_submit, { 'form_data': Forms.Serialize( form ) } )
			)
			.finally();
		} );
	}
}