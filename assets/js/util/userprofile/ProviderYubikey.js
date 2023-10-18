import $ from 'jquery';
import { ProviderBase } from "./ProviderBase";

export class ProviderYubikey extends ProviderBase {

	init() {
		$( document ).on( 'keypress', 'input#icwp_wpsf_yubi_otp', ( evt ) => {
			if ( evt.key === 'Enter' || evt.keyCode === 13 ) {
				evt.preventDefault();
				this._base_data.ajax.profile_yubikey_toggle.otp = $( evt.currentTarget ).val();
				this.sendReq( this._base_data.ajax.profile_yubikey_toggle );
				return false;
			}
		} );
		$( document ).on( 'click', 'a.shield_yubi_remove', ( evt ) => {
			evt.preventDefault();
			this._base_data.ajax.profile_yubikey_toggle.otp = $( evt.currentTarget ).data( 'yubikeyid' );
			this.sendReq( this._base_data.ajax.profile_yubikey_toggle );
			return false;
		} );
	}
}