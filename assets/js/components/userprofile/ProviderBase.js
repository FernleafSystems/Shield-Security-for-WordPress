import { AjaxService } from "../services/AjaxService";
import { BaseComponent } from "../BaseComponent";

export class ProviderBase extends BaseComponent {

	constructor( props, profileRenderer ) {
		super( props );
		this.profileRenderer = profileRenderer;
	}

	init() {
		this.exec();
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