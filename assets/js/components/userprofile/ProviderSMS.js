import $ from 'jquery';
import { ProviderBase } from "./ProviderBase";

export class ProviderSMS extends ProviderBase {

	init() {
		shieldEventsHandler_UserProfile.add_Click( 'a.shield_sms_remove', ( targetEl ) => {
			if ( confirm( this._base_data.strings.are_you_sure ) ) {
				this.sendReq( this._base_data.ajax.profile_sms2fa_remove );
			}
		} );

		shieldEventsHandler_UserProfile.add_Change( '#shield_mfasms_phone', ( targetEl ) => this.cleanPhone( targetEl ) );

		shieldEventsHandler_UserProfile.add_Click( '#shield_mfasms_verify', ( targetEl ) => {
			let reqAddParams = this._base_data.ajax.profile_sms2fa_add;

			let $countrySelect = $( 'select#shield_mfasms_country' );
			reqAddParams.sms_country = $countrySelect.val();
			reqAddParams.sms_phone = $( 'input[type=text]#shield_mfasms_phone' ).val();

			let combined = $countrySelect.find( ':selected' ).data( 'code' ) + ' ' + reqAddParams.sms_phone

			if ( !( new RegExp( "^[0-9]+$" ) ).test( reqAddParams.sms_phone ) ) {
				alert( "Phone number should contain only numbers 0-9." )
			}
			else if ( reqAddParams.sms_phone.length < 7 ) {
				alert( "Phone number doesn't seem long enough." )
			}
			else if ( confirm( 'Are you sure this country code and number are correct: ' + combined ) ) {
				targetEl.setAttribute( 'disabled', 'disabled' );
				let ajaxurl = reqAddParams.ajaxurl;
				delete reqAddParams.ajaxurl;

				$
				.post( ajaxurl, reqAddParams, ( resp ) => {
						let msg = 'Communications error with site.';

						if ( resp.data.success ) {
							let verifyCode = prompt( resp.data.message )
							if ( verifyCode !== null ) {
								let reqVerifyParams = this._base_data.ajax.profile_sms2fa_verify;
								reqVerifyParams.sms_country = $( 'select#shield_mfasms_country' ).val();
								reqVerifyParams.sms_phone = $( 'input[type=text]#shield_mfasms_phone' ).val();
								reqVerifyParams.sms_code = verifyCode;
								this.sendReq( reqVerifyParams );
							}
						}
						else {
							if ( resp.data.message !== undefined ) {
								msg = resp.data.message;
							}
							else {
								msg = 'Sending verification SMS failed';
							}
							alert( msg );
						}
					}
				)
				.always( () => targetEl.removeAttribute( 'disabled', 'disabled' ) );

				reqAddParams.ajaxurl = ajaxurl;
			}
		} );
	}

	cleanPhone( phoneInput ) {
		phoneInput.value = phoneInput.value.replace( /[^0-9]+/, '' );
		if ( phoneInput.value.length > 15 ) {
			phoneInput.value = phoneInput.value.substring( 0, 15 );
		}
	}
}