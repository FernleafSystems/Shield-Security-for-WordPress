import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";
import { Forms } from "../../util/Forms";
import { ObjectOps } from "../../util/ObjectOps";

export class ConfigImport extends BaseComponent {

	init() {
		shieldEventsHandler_Main.add_Submit( 'form#ImportSiteForm', ( form ) => {
			( new AjaxService() )
			.send( ObjectOps.Merge( this._base_data.ajax.import_from_site, { form_params: Forms.Serialize( form ) } ) )
			.finally();
		} );
		shieldEventsHandler_Main.add_Submit( 'form#ImportExportNetworkInviteAcceptForm', ( form ) => {
			( new AjaxService() )
			.send( ObjectOps.Merge( this._base_data.ajax.network_invite_accept, { form_params: Forms.Serialize( form ) } ) )
			.finally();
		} );
		shieldEventsHandler_Main.add_Submit( 'form#ImportExportNetworkInviteRejectForm', ( form ) => {
			( new AjaxService() )
			.send( ObjectOps.Merge( this._base_data.ajax.network_invite_reject, { form_params: Forms.Serialize( form ) } ) )
			.finally();
		} );
	}
}
