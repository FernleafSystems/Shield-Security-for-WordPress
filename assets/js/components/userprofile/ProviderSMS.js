import $ from 'jquery';
import { ProviderBase } from "./ProviderBase";
import { mfaAlert, mfaConfirm, mfaPrompt } from "./MfaProfileDialog";

export class ProviderSMS extends ProviderBase {

	init() {
		shieldEventsHandler_UserProfile.add_Click( 'a.shield_sms_remove', async ( targetEl ) => {
			if ( await mfaConfirm( {
				title: shieldStrings.string( 'dialog_confirm_title' ),
				message: this._base_data.strings.are_you_sure,
				confirmLabel: shieldStrings.string( 'confirm' ),
				cancelLabel: shieldStrings.string( 'cancel' ),
				danger: true,
				launcher: targetEl,
			} ) ) {
				this.sendReq( this._base_data.ajax.profile_sms2fa_remove, targetEl );
			}
		} );

		shieldEventsHandler_UserProfile.add_Change( '#shield_mfasms_phone', ( targetEl ) => this.cleanPhone( targetEl ) );

		shieldEventsHandler_UserProfile.add_Click( '#shield_mfasms_verify', async ( targetEl ) => {
			let reqAddParams = {
				...this._base_data.ajax.profile_sms2fa_add,
			};

			let $countrySelect = $( 'select#shield_mfasms_country' );
			reqAddParams.sms_country = $countrySelect.val();
			reqAddParams.sms_phone = $( 'input[type=text]#shield_mfasms_phone' ).val();

			let combined = $countrySelect.find( ':selected' ).data( 'code' ) + ' ' + reqAddParams.sms_phone

			if ( !( new RegExp( "^[0-9]+$" ) ).test( reqAddParams.sms_phone ) ) {
				await this.showAlert( this._base_data.strings.phone_digits_only, targetEl );
			}
			else if ( reqAddParams.sms_phone.length < 7 ) {
				await this.showAlert( this._base_data.strings.phone_too_short, targetEl );
			}
			else if ( await mfaConfirm( {
				title: shieldStrings.string( 'dialog_confirm_title' ),
				message: ( this._base_data.strings.confirm_phone || '' ).replace( '%s', combined ),
				confirmLabel: shieldStrings.string( 'confirm' ),
				cancelLabel: shieldStrings.string( 'cancel' ),
				launcher: targetEl,
			} ) ) {
				targetEl.setAttribute( 'disabled', 'disabled' );
				let ajaxurl = reqAddParams.ajaxurl;
				delete reqAddParams.ajaxurl;

				$
				.post( ajaxurl, reqAddParams, async ( resp ) => {
						let msg = shieldStrings.string( 'request_failed' );

						if ( resp.data.success ) {
							let verifyCode = await mfaPrompt( {
								title: this._base_data.strings.sms_code_prompt_title,
								message: resp.data.message,
								label: this._base_data.strings.sms_code_prompt_label,
								value: '',
								confirmLabel: shieldStrings.string( 'confirm' ),
								cancelLabel: shieldStrings.string( 'cancel' ),
								launcher: targetEl,
							} );
							if ( verifyCode !== null ) {
								let reqVerifyParams = this._base_data.ajax.profile_sms2fa_verify;
								reqVerifyParams.sms_country = $( 'select#shield_mfasms_country' ).val();
								reqVerifyParams.sms_phone = $( 'input[type=text]#shield_mfasms_phone' ).val();
								reqVerifyParams.sms_code = verifyCode;
								this.sendReq( reqVerifyParams, targetEl );
							}
						}
						else {
							if ( resp.data.message !== undefined ) {
								msg = resp.data.message;
							}
							else {
								msg = this._base_data.strings.sms_send_failed;
							}
							await this.showAlert( msg, targetEl );
						}
					}
				)
				.always( () => targetEl.removeAttribute( 'disabled', 'disabled' ) );
			}
		} );
	}

	cleanPhone( phoneInput ) {
		phoneInput.value = phoneInput.value.replace( /[^0-9]+/, '' );
		if ( phoneInput.value.length > 15 ) {
			phoneInput.value = phoneInput.value.substring( 0, 15 );
		}
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
