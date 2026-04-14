import { Login2faBase } from "./Login2faBase";
import { Login2faOtpSegments } from "./Login2faOtpSegments";

export class Login2faGoogleAuth extends Login2faBase {

	init() {
		this.gaInput = document.getElementById( 'icwp_wpsf_ga_otp' ) || false;
		super.init();
	}

	canRun() {
		return this.gaInput;
	}

	run() {
		shieldEventsHandler_Login2fa.add_Change( '#' + this.gaInput.id, ( targetEl ) => this.cleanCode( targetEl ) );
		shieldEventsHandler_Login2fa.add_Keypress( '#' + this.gaInput.id, ( targetEl ) => this.cleanCode( targetEl ) );
		this.setupSegmentedInputs();
	}

	cleanCode( targetEl ) {
		targetEl.value = targetEl.value.replace( /[^0-9]/g, '' ).substring( 0, 6 );
	}

	setupSegmentedInputs() {
		const group = document.querySelector( `[data-otp-group][data-otp-target="${ this.gaInput.id }"]` );
		const paneContent = group?.closest( '.mfa-pane-content' ) || null;

		if ( !( group instanceof HTMLElement ) || !( paneContent instanceof HTMLElement ) ) {
			return;
		}

		new Login2faOtpSegments( this.gaInput, {
			group,
			fallbackWrap: paneContent.querySelector( '.mfa-fallback-wrap' ),
			enhancedElements: Array.from( paneContent.querySelectorAll( '[data-enhanced-only]' ) ),
			normalize: ( value ) => value.replace( /[^0-9]/g, '' ).substring( 0, 6 ),
		} );
	}
}
