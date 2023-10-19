import { AjaxService } from "../AjaxService";
import { BaseService } from "../BaseService";

export class ProviderBase extends BaseService {

	constructor( props, profileRenderer ) {
		super( props );
		this.profileRenderer = profileRenderer;
	}

	sendReq( params ) {
		( new AjaxService() )
		.send( params )
		.finally( () => this.profileRenderer.render.call( this.profileRenderer ) );
	};

	container() {
		return document.getElementById( 'ShieldMfaUserProfileForm' ) || false;
	}

	postRender() {
	}
}