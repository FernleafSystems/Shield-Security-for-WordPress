import { ProviderBase } from "./ProviderBase";
import { ObjectOps } from "../../util/ObjectOps";

export class ProviderYubikey extends ProviderBase {

	run() {
		shieldEventsHandler_UserProfile.add_Keypress( 'input.shield_yubi_otp', ( targetEl, evt ) => {
			if ( evt.key === 'Enter' || evt.keyCode === 13 ) {
				let value = targetEl.value;
				const yubikeyUniqueID = value.substring( 0, 12 );

				let isAdd;
				let label = '';
				let valid;
				do {
					valid = false;
					if ( !( new RegExp( "^[a-zA-Z]{44}$" ) ).test( value ) ) {
						alert( this._base_data.strings.invalid_otp );
						break;
					}
					else if ( this._base_data.vars.registered_yubikeys.includes( yubikeyUniqueID ) ) {
						valid = true;
						isAdd = false;
					}
					else {
						label = prompt( this._base_data.strings.registered_yubikeys, "<Provide A Label>" );
						if ( label === null ) {
							break;
						}
						else if ( typeof label !== 'string' ) {
							alert( this._base_data.strings.err_no_label );
						}
						else if ( !( new RegExp( "^[\\s\\da-zA-Z_-]{1,16}$" ) ).test( label ) ) {
							alert( this._base_data.strings.err_invalid_label );
						}
						else {
							valid = true;
							isAdd = true;
						}
					}
				} while ( !valid );

				if ( valid ) {
					this
					.sendReq( ObjectOps.Merge( this._base_data.ajax.profile_yubikey_toggle, {
						label: label,
						otp: value,
					} ) )
					.then( ( resp ) => {
						if ( resp.success ) {
							this.updateRegisteredKey( yubikeyUniqueID, isAdd );
						}
					} );
				}
			}
		} );

		shieldEventsHandler_UserProfile.add_Click( 'a.shield_remove_yubi', ( targetEl ) => {
			if ( confirm( shieldStrings.string( 'are_you_sure' ) ) ) {
				this._base_data.ajax.profile_yubikey_toggle.otp = targetEl.dataset[ 'yubikeyid' ];
				this.sendReq( this._base_data.ajax.profile_yubikey_toggle )
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
}