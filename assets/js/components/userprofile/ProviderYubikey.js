import { ProviderBase } from "./ProviderBase";
import { ObjectOps } from "../../util/ObjectOps";
import { isValidMfaDeviceLabel, mfaAlert, mfaConfirm, mfaPrompt } from "./MfaProfileDialog";

export class ProviderYubikey extends ProviderBase {

	run() {
		shieldEventsHandler_UserProfile.add_Keypress( 'input.shield_yubi_otp', async ( targetEl, evt ) => {
			if ( evt.key === 'Enter' || evt.keyCode === 13 ) {
				let value = targetEl.value;
				const yubikeyUniqueID = value.substring( 0, 12 );

				let isAdd;
				let label = '';
				let valid = false;
				if ( !( new RegExp( "^[a-zA-Z]{44}$" ) ).test( value ) ) {
					await this.showAlert( this._base_data.strings.invalid_otp, targetEl );
				}
				else if ( this._base_data.vars.registered_yubikeys.includes( yubikeyUniqueID ) ) {
					valid = true;
					isAdd = false;
				}
				else {
					label = await this.promptForLabel( targetEl );
					if ( typeof label === 'string' && isValidMfaDeviceLabel( label ) ) {
						valid = true;
						isAdd = true;
					}
				}

				if ( valid ) {
					this
					.sendReq( ObjectOps.Merge( this._base_data.ajax.profile_yubikey_toggle, {
						label: label,
						otp: value,
					} ), targetEl )
					.then( ( resp ) => {
						if ( resp.success ) {
							this.updateRegisteredKey( yubikeyUniqueID, isAdd );
						}
					} );
				}
			}
		} );

		shieldEventsHandler_UserProfile.add_Click( '.shield_remove_yubi', async ( targetEl ) => {
			if ( await mfaConfirm( {
				title: shieldStrings.string( 'dialog_confirm_title' ),
				message: shieldStrings.string( 'are_you_sure' ),
				confirmLabel: shieldStrings.string( 'confirm' ),
				cancelLabel: shieldStrings.string( 'cancel' ),
				danger: true,
				launcher: targetEl,
			} ) ) {
				this._base_data.ajax.profile_yubikey_toggle.otp = targetEl.dataset[ 'yubikeyid' ];
				this.sendReq( this._base_data.ajax.profile_yubikey_toggle, targetEl )
					.then( ( resp ) => {
						if ( resp.success ) {
							this.updateRegisteredKey( targetEl.dataset[ 'yubikeyid' ], false );
						}
					} );
			}
		} );
	}

	updateRegisteredKey( yubikeyUniqueID, isAdd ) {
		isAdd ? this._base_data.vars.registered_yubikeys.push( yubikeyUniqueID )
			: this._base_data.vars.registered_yubikeys = this._base_data.vars.registered_yubikeys.filter( val => val !== yubikeyUniqueID );
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

	showAlert( message, launcher ) {
		return mfaAlert( {
			title: shieldStrings.string( 'dialog_alert_title' ),
			message,
			confirmLabel: shieldStrings.string( 'continue' ),
			launcher,
		} );
	}
}
