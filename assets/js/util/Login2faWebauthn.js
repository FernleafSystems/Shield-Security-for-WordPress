import { browserSupportsWebAuthn, startAuthentication } from '@simplewebauthn/browser';
import { Login2faBase } from "./Login2faBase";
import { AjaxService } from "./AjaxService";
import { ObjectOps } from "./ObjectOps";

export class Login2faWebauthn extends Login2faBase {

	init() {
		this.startWan = document.getElementById( 'btn_start_wan' ) || false;
		super.init();
	}

	canRun() {
		return browserSupportsWebAuthn() && this.startWan;
	}

	run() {
		this.startWan.addEventListener( 'click', () => {
			( new AjaxService() )
			.send( this._base_data.ajax.wan_auth_start, true, true )
			.then( async ( resp ) => {
				if ( resp.success ) {
					const challenge = resp.data.challenge;

					let attResp;
					try {
						// Pass the options to the authenticator and wait for a response
						attResp = await startAuthentication( challenge );
					}
					catch ( error ) {
						// Some basic error handling
						if ( error.name === 'InvalidStateError' ) {
							// elemError.innerText = 'Error: Authenticator was probably already registered by user';
						}
						else {
							// elemError.innerText = error;
						}

						throw error;
					}

					( new AjaxService() )
					.send(
						ObjectOps.Merge( this._base_data.ajax.wan_auth_verify, { auth: JSON.stringify( attResp ) } )
					)
					.then( ( resp ) => {
						console.log( resp );
					} )
					.finally();
				}
			} )
			.finally();
		}, false );
	}

}