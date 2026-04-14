import { Login2faBase } from "./Login2faBase";
import { AjaxService } from "../services/AjaxService";
import { Login2faOtpSegments } from "./Login2faOtpSegments";

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
		shieldEventsHandler_Login2fa.add_Change( '#' + this.emailInput.id, ( targetEl ) => this.cleanInput( targetEl ) );
		shieldEventsHandler_Login2fa.add_Keypress( '#' + this.emailInput.id, ( targetEl ) => this.cleanInput( targetEl ) );
		shieldEventsHandler_Login2fa.add_Click( '#' + this.emailSend.id, () => this.sendEmail() );
		this.setupSegmentedInputs();

		if ( Number( this.emailInput.dataset[ 'auto_send' ] ) === 1 ) {
			this.sendEmail();
		}
	}

	cleanInput( targetEl ) {
		targetEl.value = targetEl.value.toUpperCase();
		targetEl.value = targetEl.value.replace( /[^0-9A-Z]/g, '' ).substring( 0, 6 );
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
			const message = error?.responseJSON?.data?.message || 'Communications error with site.';
			alert( 'OTP email sending was unsuccessful: ' + message );
		} )
		.finally( () => {
			this.emailSend.removeAttribute( 'disabled' );
		} )
	};

	setupSegmentedInputs() {
		const group = document.querySelector( `[data-otp-group][data-otp-target="${ this.emailInput.id }"]` );
		const paneContent = group?.closest( '.mfa-pane-content' ) || null;

		if ( !( group instanceof HTMLElement ) || !( paneContent instanceof HTMLElement ) ) {
			return;
		}

		new Login2faOtpSegments( this.emailInput, {
			group,
			fallbackWrap: paneContent.querySelector( '.mfa-fallback-wrap' ),
			enhancedElements: Array.from( paneContent.querySelectorAll( '[data-enhanced-only]' ) ),
			normalize: ( value ) => value.toUpperCase().replace( /[^0-9A-Z]/g, '' ).substring( 0, 6 ),
		} );
	}
}
