import { ProviderBase } from "./ProviderBase";
import { ObjectOps } from "../ObjectOps";

export class ProviderEmail extends ProviderBase {
	run() {
		shieldEventsHandler_UserProfile.add_Change( 'input.shield-enable-mfaemail', ( targetEl ) => {
			if ( targetEl.checked !== this.wasChecked ) {
				this.sendReq(
					ObjectOps.Merge( this._base_data.ajax.profile_email2fa_toggle, {
						direction: targetEl.checked ? 'on' : 'off'
					} )
				);
			}
		} );
	}

	postRender() {
		this.wasChecked = this.container().querySelector( 'input.shield-enable-mfaemail' ).checked;
	}
}