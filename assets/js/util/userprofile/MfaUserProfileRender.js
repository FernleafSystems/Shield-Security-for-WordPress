import { BaseService } from "../BaseService";
import { AjaxService } from "../AjaxService";
import { ProviderEmail } from "./ProviderEmail";
import { ProviderYubikey } from "./ProviderYubikey";
import { ProviderGA } from "./ProviderGA";
import { ProviderBackupCodes } from "./ProviderBackupCodes";
import { ObjectOps } from "../ObjectOps";
import { ProviderWebauthn } from "./ProviderWebauthn";

export class MfaUserProfileRender extends BaseService {

	init() {
		this.container = document.getElementById( 'ShieldMfaUserProfileForm' ) || false;
		this.providers = {};
		this.exec()
	}

	canRun() {
		return this.container;
	}

	run() {
		this.render();
	}

	render() {
		( new AjaxService() )
		.bg( this._base_data.ajax.render_profile )
		.then( ( resp ) => {
			this.container.innerHTML = resp.data.html;
			return resp;
		} )
		.then( ( resp ) => {
			if ( ObjectOps.IsEmpty( this.providers ) && 'providers' in this._base_data.vars ) {
				const providers = this._base_data.vars.providers;
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
				if ( 'wan' in providers ) {
					this.providers[ 'wan' ] = new ProviderWebauthn( providers.wan, this );
				}
			}
		} )
		.finally( () => {
			Object.keys( this.providers ).forEach( ( key ) => this.providers[ key ].postRender.call( this.providers[ key ] ) );
		} );
	}
}