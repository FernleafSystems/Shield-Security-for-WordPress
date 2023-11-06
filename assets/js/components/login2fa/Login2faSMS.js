import $ from 'jquery';
import { Login2faBase } from "./Login2faBase";

/**
 * Untested
 */
export class Login2faSMS extends Login2faBase {

	$smsInput;

	init() {
		this.$smsInput = $( 'input#icwp_wpsf_sms_otp' );
	}

	run() {
		this.$smsInput.on( 'click', () => {

			if ( confirm( 'Are you sure?' ) ) {
				this.$smsInput.attr( 'disabled', 'disabled' );

				let reqParamsStart = this.$smsInput.data( 'ajax_intent_sms_send' );
				let ajaxurl = reqParamsStart.ajaxurl;
				delete reqParamsStart.ajaxurl;

				$
				.post( ajaxurl, reqParamsStart, ( resp ) => {
					let msg = 'Communications error with site.';

					if ( resp.data.success ) {
						alert( resp.data.message );
						let newText = document.createElement( "input" );
						newText.classList.add( 'form-control' );
						let $newText = $( newText );
						$newText.attr( 'autocomplete', 'off' );
						$newText.attr( 'placeholder', 'Enter SMS One-Time Password' );
						$newText.attr( 'name', this.$smsInput.attr( 'name' ) );
						$newText.attr( 'id', this.$smsInput.attr( 'id' ) );
						$newText.insertBefore( this.$smsInput );
						this.$smsInput.remove();
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
				} )
				.always( () => {
					reqParamsStart.ajaxurl = ajaxurl;
					ShieldUI.SetBusy()
					this.$smsInput.removeAttr( 'disabled' );
				} );
			}
		} );
	}
}