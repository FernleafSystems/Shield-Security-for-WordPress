import { browserSupportsWebAuthn, startRegistration } from '@simplewebauthn/browser';
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { ProviderBase } from "./ProviderBase";
import { isValidMfaDeviceLabel, mfaConfirm, mfaPrompt } from "./MfaProfileDialog";

export class ProviderPasskeys extends ProviderBase {

	canRun() {
		return browserSupportsWebAuthn() && ( this.container().querySelector( 'button.shield_passkey_reg' ) !== null );
	}

	run() {
		shieldEventsHandler_UserProfile.add_Click( '.shield_remove_passkey', async ( targetEl ) => {
			if ( await mfaConfirm( {
				title: shieldStrings.string( 'dialog_confirm_title' ),
				message: this._base_data.strings.are_you_sure,
				confirmLabel: shieldStrings.string( 'confirm' ),
				cancelLabel: shieldStrings.string( 'cancel' ),
				danger: true,
				launcher: targetEl,
			} ) ) {
				this.sendReq( ObjectOps.Merge( this._base_data.ajax.passkey_remove_registration, targetEl.dataset ), targetEl );
			}
		} );

		shieldEventsHandler_UserProfile.add_Click( 'button.shield_passkey_reg', ( targetEl ) => this.runRegister( targetEl ) );
	}

	postRender() {
		if ( this.canRun() ) {
			this.container().querySelector( 'button.shield_passkey_reg' ).removeAttribute( 'disabled' );
		}
	}

	runRegister( launcher = null ) {
		( new AjaxService() )
		.send( ObjectOps.Merge( this._base_data.ajax.passkey_start_registration ), false, true )
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

				let label = resp.data.passkey_label;
				if ( !isValidMfaDeviceLabel( label ) ) {
					label = '';
				}
				if ( label.length === 0 ) {
					label = await this.promptForLabel( launcher );
				}

				if ( typeof label === 'string' && isValidMfaDeviceLabel( label ) ) {
					this.sendReq( ObjectOps.Merge( this._base_data.ajax.passkey_verify_registration, {
						label: label, reg: JSON.stringify( attResp ),
					} ), launcher );
				}
			}
		} )
		.finally();
	}

	promptForLabel( launcher ) {
		return mfaPrompt( {
			title: shieldStrings.string( 'dialog_prompt_title' ),
			message: this._base_data.strings.label_prompt_dialog,
			label: this._base_data.strings.label_prompt_label,
			value: '',
			confirmLabel: shieldStrings.string( 'confirm' ),
			cancelLabel: shieldStrings.string( 'cancel' ),
			launcher,
			validate: ( value ) => {
				if ( typeof value !== 'string' || value.length === 0 ) {
					return this._base_data.strings.err_no_label;
				}
				return isValidMfaDeviceLabel( value ) || this._base_data.strings.err_invalid_label;
			},
		} );
	}

}
