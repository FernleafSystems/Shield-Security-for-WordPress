import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";

export class LicenseHandler extends BaseService {
	init() {
		shieldEventsHandler_Main.add_Click( '.license-action', ( targetEl ) => {
			( new AjaxService() )
			.send( this._base_data.ajax[ targetEl.dataset[ 'action' ] ] )
			.finally();
		} );
	}
}