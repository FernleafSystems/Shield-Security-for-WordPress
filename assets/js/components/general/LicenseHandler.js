import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";

export class LicenseHandler extends BaseComponent {
	init() {
		shieldEventsHandler_Main.add_Click( '.license-action', ( targetEl ) => {
			if ( targetEl.dataset[ 'action' ] !== 'clear' || confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				( new AjaxService() )
				.send( this._base_data.ajax[ targetEl.dataset[ 'action' ] ] )
				.finally();
			}
		} );
	}
}