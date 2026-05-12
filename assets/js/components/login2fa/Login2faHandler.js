import { BaseComponent } from "../BaseComponent";
import { Login2faEmail } from "./Login2faEmail";
import { Login2faGoogleAuth } from "./Login2faGoogleAuth";
import { Login2faPasskey } from "./Login2faPasskey";
import { ShieldLoginIntentUi } from "./ShieldLoginIntentUi";

export class Login2faHandler extends BaseComponent {

	init() {
		this.timeRemainingP = document.getElementById( 'TimeRemaining' ) || false;
		this.countdownMirror = document.querySelector( '[data-countdown-mirror]' ) || false;
		this.isShieldCustomPage = document.querySelector( 'form#loginform.shield-2fa-custom' ) !== null;
		const firstInput = document.querySelector( 'form#loginform input[type=text]' );
		if ( !this.isShieldCustomPage && firstInput ) {
			firstInput.focus();
		}
		this.updateCountdownMirror();
		this.exec();
	}

	run() {
		new Login2faEmail( this._base_data );
		new Login2faGoogleAuth();
		new Login2faPasskey( this._base_data );
		if ( this.isShieldCustomPage ) {
			new ShieldLoginIntentUi( this._base_data );
		}
		if ( this.timeRemainingP ) {
			this.countdownTimer();
		}
	}

	updateCountdownMirror( text = null ) {
		if ( !( this.countdownMirror instanceof HTMLElement ) ) {
			return;
		}

		const mirrorText = text === null
			? this.timeRemainingP?.textContent?.trim() || ''
			: String( text ).trim();

		this.countdownMirror.textContent = mirrorText;
	}

	countdownTimer() {
		// Set the date we're counting down to
		let timeRemaining = this._base_data.vars.time_remaining;
		const loginExpired = () => {
			const mainSubmit = document.getElementById( "mainSubmit" );
			if ( mainSubmit instanceof HTMLButtonElement ) {
				mainSubmit.setAttribute( 'disabled', 'disabled' );
			}
			this.timeRemainingP.className = "text-center alert alert-danger";
		};

		// Update the countdown every 1 second
		let x = setInterval( () => {
			timeRemaining -= 1;
			if ( timeRemaining < 0 ) {
				clearInterval( x );
				loginExpired();
				this.timeRemainingP.innerHTML = this._base_data.strings.login_expired;
				this.updateCountdownMirror();
			}
			else {
				let minutes = Math.floor( timeRemaining / 60 );
				let seconds = Math.floor( timeRemaining % 60 );
				let remaining = 'minutesseconds';
				if ( minutes > 0 ) {
					remaining = remaining.replace( /minutes/i, minutes + ' ' + this._base_data.strings.minutes )
										 .replace( /seconds/i, ' ' + seconds + ' ' + this._base_data.strings.seconds );
				}
				else {
					remaining = remaining.replace( /minutes/i, '' )
										 .replace( /seconds/i, timeRemaining.toFixed( 0 ) + ' ' + this._base_data.strings.seconds );
				}
				const countdown = this.timeRemainingP.querySelector( ".countdown" );
				if ( countdown ) {
					countdown.textContent = remaining;
				}
				this.updateCountdownMirror( remaining );
			}
		}, 1000 );
	}
}
