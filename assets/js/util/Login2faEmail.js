import { Login2faBase } from "./Login2faBase";
import { AjaxService } from "./AjaxService";

export class Login2faEmail extends Login2faBase {

	init() {
		this.emailInput = document.getElementById( 'icwp_wpsf_email_otp' ) || false;
		this.emailSend = document.getElementById( 'ajax_intent_email_send' ) || false;
		super.init();
	}

	canRun() {
		return this.emailInput && this.emailSend;
	}

	run() {
		this.emailInput.value = '';

		shieldEventsHandler_Login2fa.add_Change( '#' + this.emailInput.id, ( targetEl ) => this.cleanInput( targetEl ) );
		shieldEventsHandler_Login2fa.add_Keypress( '#' + this.emailInput.id, ( targetEl ) => this.cleanInput( targetEl ) );
		shieldEventsHandler_Login2fa.add_Click( '#' + this.emailSend.id, () => this.sendEmail() );

		if ( Number( this.emailInput.dataset[ 'auto_send' ] ) === 1 ) {
			this.sendEmail();
		}
	}

	cleanInput( targetEl ) {
		targetEl.value = targetEl.value.toUpperCase();
		targetEl.value = targetEl.value.replace( /[^0-9A-Z]/, '' ).substring( 0, 6 );
	}

	sendEmail() {
		this.emailSend.setAttribute( 'disabled', 'disabled' );

		( new AjaxService() )
		.send( this._base_data.ajax.email_code_send, true, true )
		.then( ( resp ) => {

			/** TODO: TEST THIS?*/
			let msg = 'Communications error with site.';

			if ( resp.data.success ) {
				alert( resp.data.message );
			}
			else {
				if ( resp.data.message !== undefined ) {
					msg = resp.data.message;
				}
				else {
					msg = 'Sending Email 2FA failed';
				}
				alert( msg );
			}
		} )
		.catch( ( error ) => {
			alert( 'OTP email sending was unsuccessful: ' + data.responseJSON.data.message );
		} )
		.finally( () => {
			this.emailSend.setAttribute( 'disabled', false );
		} )
	};
}