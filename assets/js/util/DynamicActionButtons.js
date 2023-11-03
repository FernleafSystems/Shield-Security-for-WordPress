import { BaseService } from "./BaseService";
import { AjaxService } from "./AjaxService";

export class DynamicActionButtons extends BaseService {

	init() {
		shieldEventsHandler_Main.add_Click( '.shield_dynamic_action_button', ( targetEl ) => {
			let data = targetEl.dataset;
			if ( !( data[ 'confirm' ] ?? false ) || confirm( 'Are you sure?' ) ) {
				delete data[ 'confirm' ];
				( new AjaxService() ).send( data ).finally();
			}
		} );
	}
}