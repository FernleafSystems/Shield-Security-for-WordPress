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