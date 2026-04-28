import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";
import { confirmDialog } from "../ui/ShieldDialog";

export class DynamicActionButtons extends BaseComponent {

	init() {
		shieldEventsHandler_Main.add_Click( '.shield_dynamic_action_button', async ( targetEl ) => {
			const requestData = {
				...targetEl.dataset,
			};
			const confirmValue = String( requestData.confirm || '' ).trim();
			if ( confirmValue.length > 0 ) {
				const confirmed = await confirmDialog( {
					message: normalizeConfirmMessage( confirmValue ),
					danger: true,
					launcher: targetEl,
				} );
				if ( !confirmed ) {
					return;
				}
			}
			delete requestData.confirm;
			( new AjaxService() ).send( requestData ).finally();
		} );
	}
}

function normalizeConfirmMessage( confirmValue ) {
	return [ '1', 'true', 'yes' ].includes( confirmValue.toLowerCase() )
		? shieldStrings.string( 'are_you_sure' )
		: confirmValue;
}
