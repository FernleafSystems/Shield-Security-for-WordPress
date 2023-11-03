import { ProviderBase } from "./ProviderBase";

export class ProviderYubikey extends ProviderBase {

	run() {
		shieldEventsHandler_UserProfile.add_Keypress( 'input.shield_yubi_otp', ( targetEl, evt ) => {
			if ( evt.key === 'Enter' || evt.keyCode === 13 ) {
				evt.preventDefault();
				this._base_data.ajax.profile_yubikey_toggle.otp = targetEl.value;
				this.sendReq( this._base_data.ajax.profile_yubikey_toggle );
				return false;
			}
		} );
		shieldEventsHandler_UserProfile.add_Click( 'a.shield_remove_yubi', ( targetEl ) => {
			if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				this._base_data.ajax.profile_yubikey_toggle.otp = targetEl.dataset[ 'yubikeyid' ];
				this.sendReq( this._base_data.ajax.profile_yubikey_toggle );
			}
		} );
	}
}