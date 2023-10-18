import $ from 'jquery';
import { BaseService } from "./BaseService";
import { Login2faEmail } from "./Login2faEmail";
import { Login2faGoogleAuth } from "./Login2faGoogleAuth";

export class Login2faHandler extends BaseService {

	init() {
		this.timeRemainingP = document.getElementById( 'TimeRemaining' ) || false;
		$( 'form#loginform input[type=text]:first' ).focus();
		this.exec();
	}

	run() {
		new Login2faEmail();
		new Login2faGoogleAuth();
		if ( this.timeRemainingP ) {
			this.countdownTimer();
		}
	}

	countdownTimer() {
		// Set the date we're counting down to
		let timeRemaining = this._base_data.vars.time_remaining;
		console.log( timeRemaining );
		// Update the countdown every 1 second
		let x = setInterval( () => {
			timeRemaining -= 1;
			if ( timeRemaining < 0 ) {
				clearInterval( x );
				loginExpired();
				this.timeRemainingP.innerHTML = this._base_data.strings.login_expired;
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
			}
		}, 1000 );

		function loginExpired() {
			document.getElementById( "mainSubmit" ).setAttribute( 'disabled', 'disabled' );
			document.getElementById( "TimeRemaining" ).className = "text-center alert alert-danger";
		}
	}
}