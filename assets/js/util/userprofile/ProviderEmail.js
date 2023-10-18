import $ from 'jquery';
import { ProviderBase } from "./ProviderBase";

export class ProviderEmail extends ProviderBase {

	init() {
		let $checkbox = $( 'input[type=checkbox]#shield_enable_mfaemail' );
		$( document ).on( 'change', $checkbox, () => {
			if ( $checkbox.is( ':checked' ) !== $checkbox.is( ':checked' ) ) {
				$checkbox.prop( 'disabled', true );
				this._base_data.ajax.profile_email2fa_toggle.direction = $checkbox.is( ':checked' ) ? 'on' : 'off';
				this.sendReq( this._base_data.ajax.profile_email2fa_toggle );
			}
		} );
	}
}