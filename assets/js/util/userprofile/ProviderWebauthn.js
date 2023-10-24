import { browserSupportsWebAuthn, startRegistration } from '@simplewebauthn/browser';
import { AjaxService } from "../AjaxService";
import { ProviderBase } from "./ProviderBase";
import { ObjectOps } from "../ObjectOps";
import $ from "jquery";

export class ProviderWebauthn extends ProviderBase {

	init() {
		this.exec();
	}

	canRun() {
		return browserSupportsWebAuthn() && this.container().querySelector( 'button#icwp_wan_key_reg' );
	}

	run() {
		$( this.container() ).on( 'click', 'a.shield_remove_wan', ( evt ) => {
			evt.preventDefault();
			this.sendReq( ObjectOps.Merge( this._base_data.ajax.wan_remove_registration, evt.currentTarget.dataset ) );
			return false;
		} );
	}

	postRender() {
		if ( this.canRun() ) {
			this.runRegister();
		}
	}

	runRegister() {
		const register = this.container().querySelector( 'button#icwp_wan_key_reg' );
		register.removeAttribute( 'disabled' );

		register.addEventListener( 'click', async () => {

			( new AjaxService() )
			.send( this._base_data.ajax.wan_start_registration, true, true )
			.then( async ( resp ) => {
				if ( resp.success ) {
					const challenge = resp.data.challenge;

					let attResp;
					try {
						// Pass the options to the authenticator and wait for a response
						attResp = await startRegistration( challenge );
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
						ObjectOps.Merge( this._base_data.ajax.wan_verify_registration, { reg: JSON.stringify( attResp ) } )
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