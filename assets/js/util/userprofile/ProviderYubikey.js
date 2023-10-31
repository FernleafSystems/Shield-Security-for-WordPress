import $ from 'jquery';
import { ProviderBase } from "./ProviderBase";

export class ProviderYubikey extends ProviderBase {

	init() {
		$( this.container() ).on( 'keypress', 'input#icwp_wpsf_yubi_otp', ( evt ) => {
			if ( evt.key === 'Enter' || evt.keyCode === 13 ) {
				evt.preventDefault();
				this._base_data.ajax.profile_yubikey_toggle.otp = $( evt.currentTarget ).val();
				this.sendReq( this._base_data.ajax.profile_yubikey_toggle );
				return false;
			}
		} );
		$( this.container() ).on( 'click', 'a.shield_remove_yubi', ( evt ) => {
			evt.preventDefault();
			if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				this._base_data.ajax.profile_yubikey_toggle.otp = $( evt.currentTarget ).data( 'yubikeyid' );
				this.sendReq( this._base_data.ajax.profile_yubikey_toggle );
			}
			return false;
		} );
	}
}