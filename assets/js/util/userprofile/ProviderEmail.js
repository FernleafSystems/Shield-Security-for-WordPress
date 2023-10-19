import { ProviderBase } from "./ProviderBase";
import { ObjectOps } from "../ObjectOps";

export class ProviderEmail extends ProviderBase {

	postRender() {
		const cb = this.container().querySelector( 'input.shield-enable-mfaemail' );
		if ( cb ) {
			const wasChecked = cb.checked;
			cb.addEventListener( 'change', () => {
				if ( cb.checked !== wasChecked ) {
					this.sendReq(
						ObjectOps.Merge( this._base_data.ajax.profile_email2fa_toggle, {
							direction: cb.checked ? 'on' : 'off'
						} )
					);
				}
			}, false );
		}
	}
}