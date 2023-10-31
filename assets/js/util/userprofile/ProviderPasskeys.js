import { browserSupportsWebAuthn, startRegistration } from '@simplewebauthn/browser';
import { AjaxService } from "../AjaxService";
import { ObjectOps } from "../ObjectOps";
import { ProviderBase } from "./ProviderBase";

export class ProviderPasskeys extends ProviderBase {

	init() {
		this.exec();
	}

	canRun() {
		return browserSupportsWebAuthn() && ( this.container().querySelector( 'button.shield_passkey_reg' ) !== null );
	}

	run() {
	}

	postRender() {
		if ( this.canRun() ) {
			this.attachRemove();
			this.runRegister();
		}
	}

	attachRemove() {
		this.container().querySelectorAll( 'a.shield_remove_passkey' ).forEach( ( remove ) => {
			remove.addEventListener( 'click', ( evt ) => this.runRemove( evt.currentTarget.dataset ), false );
		} );
	}

	runRemove( data ) {
		if ( confirm( this._base_data.strings.are_you_sure ) ) {
			this.sendReq( ObjectOps.Merge( this._base_data.ajax.passkey_remove_registration, data ) );
		}
	}

	runRegister() {
		const register = this.container().querySelector( 'button.shield_passkey_reg' );
		register.removeAttribute( 'disabled' );

		register.addEventListener( 'click', () => {
			( new AjaxService() )
			.send(
				ObjectOps.Merge( this._base_data.ajax.passkey_start_registration ), true, true
			)
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
						}
						else {
						}
						throw error;
					}

					let label, valid;
					do {
						valid = false;
						label = prompt( this._base_data.strings.prompt_dialog, "<Insert Label>" );
						if ( typeof label !== 'string' ) {
							alert( this._base_data.strings.err_no_label )
						}
						else if ( !( new RegExp( "^[\\s\\da-zA-Z_-]{1,16}$" ) ).test( label ) ) {
							alert( this._base_data.strings.err_invalid_label )
						}
						else {
							valid = true;
						}
					} while ( !valid );

					this.sendReq(
						ObjectOps.Merge( this._base_data.ajax.passkey_verify_registration, {
							label: label,
							reg: JSON.stringify( attResp ),
						} )
					);
				}
			} )
			.finally();

		}, false );

	}
}