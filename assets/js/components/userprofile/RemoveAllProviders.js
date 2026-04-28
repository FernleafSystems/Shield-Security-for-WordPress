import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";
import { mfaAlert, mfaConfirm } from "./MfaProfileDialog";

export class RemoveAllProviders extends BaseComponent {

	init() {
		shieldEventsHandler_UserProfile.add_Click( '.shield_mfa_remove_all', async ( targetEl ) => {
			if ( await mfaConfirm( {
				title: shieldStrings.string( 'dialog_confirm_title' ),
				message: this._base_data.strings.are_you_sure,
				confirmLabel: shieldStrings.string( 'confirm' ),
				cancelLabel: shieldStrings.string( 'cancel' ),
				danger: true,
				launcher: targetEl,
			} ) ) {
				this._base_data.ajax.mfa_remove_all.user_id = targetEl.dataset[ 'user_id' ];
				( new AjaxService() )
				.send( this._base_data.ajax.mfa_remove_all, false, true )
				.then( ( resp ) => {
					const message = typeof resp?.data?.message === 'string' ? resp.data.message : '';
					if ( message.length > 0 && resp?.data?.show_toast !== false ) {
						return mfaAlert( {
							title: shieldStrings.string( resp?.success ? 'dialog_alert_title' : 'request_failed' ),
							message,
							confirmLabel: shieldStrings.string( 'continue' ),
							launcher: targetEl,
						} );
					}
					return resp;
				} )
				.finally();
			}
		} );
	}
}
