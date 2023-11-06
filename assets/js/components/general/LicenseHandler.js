import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";

export class LicenseHandler extends BaseComponent {
	init() {
		shieldEventsHandler_Main.add_Click( '.license-action', ( targetEl ) => {
			( new AjaxService() )
			.send( this._base_data.ajax[ targetEl.dataset[ 'action' ] ] )
			.finally();
		} );
	}
}