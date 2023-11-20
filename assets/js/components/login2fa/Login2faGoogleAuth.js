import { Login2faBase } from "./Login2faBase";

export class Login2faGoogleAuth extends Login2faBase {

	init() {
		this.gaInput = document.getElementById( 'icwp_wpsf_ga_otp' ) || false;
		super.init();
	}

	canRun() {
		return this.gaInput;
	}

	run() {
		this.gaInput.value = '';
		shieldEventsHandler_Login2fa.add_Change( '#' + this.gaInput.id, ( targetEl ) => this.cleanCode( targetEl ) );
		shieldEventsHandler_Login2fa.add_Keypress( '#' + this.gaInput.id, ( targetEl ) => this.cleanCode( targetEl ) );
	}

	cleanCode( targetEl ) {
		targetEl.value = targetEl.value.replace( /[^0-9]/, '' ).substring( 0, 6 );
	}
}