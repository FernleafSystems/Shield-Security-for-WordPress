import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";

export class ProviderBase extends BaseAutoExecComponent {

	constructor( props, profileRenderer ) {
		super( props );
		this.profileRenderer = profileRenderer;
	}

	sendReq( params ) {
		return ( new AjaxService() )
		.send( params )
		.finally( () => this.profileRenderer.render.call( this.profileRenderer ) );
		/*

		return ( new AjaxService() )
		.send( params )
		.then( ( resp ) => {
			this.profileRenderer.render.call( this.profileRenderer );
			return resp;
		} );
		 */
	};

	container() {
		return document.getElementById( 'ShieldMfaUserProfileForm' ) || false;
	}

	postRender() {
	}
}