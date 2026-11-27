import { BaseComponent } from "../BaseComponent";
import { AjaxService } from "../services/AjaxService";
import { ProviderEmail } from "./ProviderEmail";
import { ProviderYubikey } from "./ProviderYubikey";
import { ProviderGA } from "./ProviderGA";
import { ProviderBackupCodes } from "./ProviderBackupCodes";
import { ProviderPasskeys } from "./ProviderPasskeys";

export class MfaUserProfileRender extends BaseComponent {

	init() {
		this.container = document.getElementById( 'ShieldMfaUserProfileForm' ) || false;
		this.providers = {};
		this.exec();
	}

	canRun() {
		return this.container;
	}

	run() {
		this.render();
	}

	render() {
		return ( new AjaxService() )
		.bg( this._base_data.ajax.render_profile )
		.then( ( resp ) => {
			this.container.innerHTML = resp.data.html;
			// These fields are sent as they're used, never with the WordPress profile form they're drawn inside.
			// Left in that form, they make it differ from how it was first drawn, so WordPress warns of unsaved
			// changes on leaving a page nobody has touched. An empty form attribute takes each out of the form.
			this.container.querySelectorAll( 'input, select, textarea, button' )
				.forEach( ( control ) => control.setAttribute( 'form', '' ) );
			const providers = resp.data.render_data.vars.providers;
			this.buildProviderHandlers( providers );
			return resp;
		} )
		.finally( () => {
			Object.keys( this.providers ).forEach( ( key ) => this.providers[ key ].postRender.call( this.providers[ key ] ) );
		} );
	}

	buildProviderHandlers( providers ) {
		this.providers = {};
		if ( 'email' in providers ) {
			this.providers[ 'email' ] = new ProviderEmail( providers.email, this );
		}
		if ( 'yubi' in providers ) {
			this.providers[ 'yubi' ] = new ProviderYubikey( providers.yubi, this );
		}
		if ( 'ga' in providers ) {
			this.providers[ 'ga' ] = new ProviderGA( providers.ga, this );
		}
		if ( 'backupcode' in providers ) {
			this.providers[ 'backupcode' ] = new ProviderBackupCodes( providers.backupcode, this );
		}
		if ( 'passkey' in providers ) {
			this.providers[ 'passkey' ] = new ProviderPasskeys( providers.passkey, this );
		}
	}
}
