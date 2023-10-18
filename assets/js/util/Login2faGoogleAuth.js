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
		this.gaInput.addEventListener( 'keypress', this.cleanCode, false );
		this.gaInput.addEventListener( 'change', this.cleanCode, false );
	}

	cleanCode() {
		this.value = this.value.replace( /[^0-9]/, '' ).substring( 0, 6 );
	}
}