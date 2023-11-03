import { AjaxService } from "./AjaxService";
import { BaseService } from "./BaseService";
import { Forms } from "./Forms";
import { ObjectOps } from "./ObjectOps";

export class ConfigImport extends BaseService {

	init() {
		shieldEventsHandler_Main.add_Submit( 'form#ImportSiteForm', ( form ) => {
			( new AjaxService() )
			.send( ObjectOps.Merge( this._base_data.ajax.import_from_site, { form_params: Forms.Serialize( form ) } ) )
			.finally();
		} );
	}
}