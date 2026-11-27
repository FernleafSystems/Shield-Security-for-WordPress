import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { mfaAlert } from "./MfaProfileDialog";

export class ProviderBase extends BaseAutoExecComponent {

	constructor( props, profileRenderer ) {
		super( props );
		this.profileRenderer = profileRenderer;
	}

	/**
	 * The section is drawn again once the request is done, as most requests change what it shows.
	 * @param keepFormOnFailure leaves it as it is after a request that was refused, where drawing it again would
	 *                          replace what the user is in the middle of (see ProviderGA).
	 */
	sendReq( params, launcher = null, keepFormOnFailure = false ) {
		let wasRefused = false;
		return ( new AjaxService() )
		.send( params, false, true )
		.then( ( resp ) => {
			wasRefused = resp?.success !== true;
			const message = typeof resp?.data?.message === 'string' ? resp.data.message : '';
			if ( message.length > 0 && resp?.data?.show_toast !== false ) {
				return mfaAlert( {
					title: shieldStrings.string( resp?.success ? 'dialog_alert_title' : 'request_failed' ),
					message,
					confirmLabel: shieldStrings.string( 'continue' ),
					launcher,
				} ).then( () => resp );
			}
			return resp;
		} )
		.finally( () => {
			if ( !( keepFormOnFailure && wasRefused ) ) {
				this.profileRenderer.render.call( this.profileRenderer );
			}
		} );
	};

	container() {
		return document.getElementById( 'ShieldMfaUserProfileForm' ) || false;
	}

	postRender() {
	}
}
