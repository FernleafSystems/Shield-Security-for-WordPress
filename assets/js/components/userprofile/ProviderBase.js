import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { mfaAlert } from "./MfaProfileDialog";

export class ProviderBase extends BaseAutoExecComponent {

	constructor( props, profileRenderer ) {
		super( props );
		this.profileRenderer = profileRenderer;
	}

	sendReq( params, launcher = null ) {
		return ( new AjaxService() )
		.send( params, false, true )
		.then( ( resp ) => {
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
		.finally( () => this.profileRenderer.render.call( this.profileRenderer ) );
	};

	container() {
		return document.getElementById( 'ShieldMfaUserProfileForm' ) || false;
	}

	postRender() {
	}
}
