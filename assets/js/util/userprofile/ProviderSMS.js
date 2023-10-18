import $ from 'jquery';
import { ProviderBase } from "./ProviderBase";

export class ProviderSMS extends ProviderBase {

	init() {
		$( 'a.shield_sms_remove' ).on( 'click', ( evt ) => {
			evt.preventDefault();
			if ( confirm( this._base_data.strings.are_you_sure ) ) {
				this.sendReq( this._base_data.ajax.profile_sms2fa_remove );
			}
			return false;
		} );

		$( document ).on( 'change keyup', '#shield_mfasms_phone', ( evt ) => {
			let $this = $( evt.currentTarget );
			const regex = /[^0-9]+/;
			$this.val( $this.val().replace( regex, '' ) );
			if ( $this.val().length > 15 ) {
				$this.val( $this.val().substring( 0, 15 ) );
			}
		} );

		$( document ).on( 'click', '#shield_mfasms_verify', ( evt ) => {
			let $this = $( evt.currentTarget );
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
				$this.attr( 'disabled', 'disabled' );
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
				.always( () => $this.removeAttr( 'disabled', 'disabled' ) );

				reqAddParams.ajaxurl = ajaxurl;
			}
		} );
	}
}