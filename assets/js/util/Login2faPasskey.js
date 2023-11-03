import { browserSupportsWebAuthn, startAuthentication } from '@simplewebauthn/browser';
import { Base64 } from "js-base64";
import { Login2faBase } from "./Login2faBase";
import { AjaxService } from "./AjaxService";

export class Login2faPasskey extends Login2faBase {

	init() {
		this.buttonStartPasskey = document.querySelector( 'button[name=icwp_wpsf_start_passkey]' );
		this.inputWanAuthAtt = document.querySelector( 'input[name=icwp_wpsf_passkey_otp]' );
		super.init();
	}

	canRun() {
		return ( this.buttonStartPasskey !== null ) && ( this.inputWanAuthAtt !== null );
	}

	run() {
		if ( browserSupportsWebAuthn() ) {
			if ( this._base_data.flags.passkey_auth_auto ) {
				this.auth();
			}
			shieldEventsHandler_Login2fa.add_Click( 'button[name=icwp_wpsf_start_passkey]', () => this.auth() );
		}
		else {
			this.buttonStartPasskey.setAttribute( 'disabled', 'disabled' );
		}
	}

	auth() {
		this.buttonStartPasskey.setAttribute( 'disabled', 'disabled' );
		let reEnableButton = true;

		( new AjaxService() )
		.send( this._base_data.ajax.passkey_auth_start, true, true )
		.then( async ( resp ) => {
			if ( resp.success ) {
				const challenge = resp.data.challenge;

				let attResp;
				try {
					// Pass the options to the authenticator and wait for a response
					attResp = await startAuthentication( challenge );
					this.inputWanAuthAtt.value = Base64.encode( JSON.stringify( attResp ) );
					this.buttonStartPasskey
						.closest( 'form' )
						.requestSubmit();
					reEnableButton = false;
				}
				catch ( error ) {
					// Some basic error handling
					if ( error.name === 'InvalidStateError' ) {
					}
					else {
					}
				}
			}
		} )
		.catch( ( error ) => {
		} )
		.finally( () => {
			if ( reEnableButton ) {
				this.buttonStartPasskey.removeAttribute( 'disabled' );
			}
		} );
	}
}