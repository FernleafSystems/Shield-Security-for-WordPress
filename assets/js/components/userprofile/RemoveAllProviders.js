import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";

export class RemoveAllProviders extends BaseComponent {

	init() {
		shieldEventsHandler_UserProfile.add_Click( '.shield_mfa_remove_all', ( targetEl ) => {
			if ( confirm( this._base_data.strings.are_you_sure ) ) {
				this._base_data.ajax.mfa_remove_all.user_id = targetEl.dataset[ 'user_id' ];
				( new AjaxService() )
				.send( this._base_data.ajax.mfa_remove_all )
				.finally();
			}
		} );
	}
}