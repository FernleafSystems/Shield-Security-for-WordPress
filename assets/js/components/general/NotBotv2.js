import { solveChallenge } from 'altcha-lib';
import { AjaxParseResponseService } from "../services/AjaxParseResponseService";
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { GetCookie } from "../../util/GetCookie";
import { ObjectOps } from "../../util/ObjectOps";
import { PageQueryParam } from "../../util/PageQueryParam";

export class NotBotv2 extends BaseAutoExecComponent {

	init() {
		this.window_focus_at = Date.now();
		this.window_blur_at = 0;

		this.notbot_altcha_challenge_request_data = null;

		this.request_count = 0;
		this.failed_request_count = 0;

		this.shield_ajaxurl = this._base_data.ajax.not_bot.ajaxurl;

		super.init();
	}

	run() {
		window.addEventListener( 'focus', () => {
			this.window_focus_at = Date.now();
		} );
		window.addEventListener( 'blur', () => {
			this.window_blur_at = Date.now();
		} );

		this.fire();
	};

	fire() {
		if ( this.request_count < 10 && this.failed_request_count < 5 ) {
			this.performPathAltcha();
		}
	}

	performPathAltcha() {
		if ( this.isAltchaChallengeRequired() ) {

			if ( this.hasAltchaChallengeData() ) {
				// run altcha challenge
				this.request_count++;
				solveChallenge(
					this.notbot_altcha_challenge_request_data.challenge,
					this.notbot_altcha_challenge_request_data.salt,
					this.notbot_altcha_challenge_request_data.algorithm,
					this.notbot_altcha_challenge_request_data.maxnumber
				).promise
				 .then( solution => {
					 this.request_count++;

					 const reqData = ObjectOps.Merge( this.notbot_altcha_challenge_request_data, solution );
					 delete reqData.ajaxurl;
					 delete reqData._wpnonce;
					 delete reqData._rest_url;

					 fetch( this.shield_ajaxurl, this.constructFetchRequestData( reqData ) )
					 .then( raw => raw.text() )
					 .then( rawText => {
						 if ( ObjectOps.IsEmpty( AjaxParseResponseService.ParseIt( rawText ) ) ) {
							 throw new Error( 'Data in the altcha request could not be parsed.' );
						 }
						 return rawText;
					 } )
					 .then( () => this.reFire() )
					 .catch( error => {
						 console.log( 'hasAltchaChallengeData() error: ' + error );
					 } )
					 .finally( () => {
						 this.notbot_altcha_challenge_request_data = null;
					 } );
				 } );
			}
			else {
				this.performPathNotbot().finally();
			}
		}
		else if ( this.isNotBotRequired() ) {
			this.performPathNotbot().finally();
		}
		else {
			this.reFire();
		}
	}

	reFire( reFireTimeout = 120000 ) {
		this.start_refire_at = Date.now();
		window.setTimeout( () => {

			if ( reFireTimeout === 0 || this.windowHasHadFocus() ) {
				//console.log( reFireTimeout === 0 ? 'forced refire timeout=0' : "debug: had focus: fire()" );
				this.fire();
			}
			else {
				//console.log( "debug: window had no focus so re-firing in 2.5 seconds" );
				this.reFire( 2500 );
			}

		}, reFireTimeout );
	}

	async performPathNotbot() {
		return this.fetch_NotBot();
	}

	hasAltchaChallengeData() {
		return this.verifyAltchaChallengeData( this.notbot_altcha_challenge_request_data );
	}

	verifyAltchaChallengeData( altcha ) {
		let has = altcha !== null;
		if ( has ) {
			const required = [
				'challenge',
				'salt',
				'algorithm',
				'maxnumber',
				'expires',
			].forEach( ( key ) => {
				if ( !Object.keys( altcha ).includes( key ) ) {
					has = false;
				}
			} );
			has = has && ( Math.round( Date.now() / 1000 ) < altcha.expires );
		}
		return has;
	}

	isNotBotRequired() {
		return PageQueryParam.Retrieve( 'force_notbot' ) === '1' || !this.getNonRequiredFlagsFromCookie().includes( 'notbot' );
	}

	isAltchaChallengeRequired() {
		return PageQueryParam.Retrieve( 'force_notbot' ) === '1' || !this.getNonRequiredFlagsFromCookie().includes( 'altcha' );
	}

	/**
	 * We now include the expiry of the cookie within the cookie itself. This is because Chrome doesn't update the
	 * cookie data to account for cookie expiration within the same page load. So we must provide a mechanism for
	 * informing the script that the known cookie status has expired.
	 */
	getNonRequiredFlagsFromCookie() {
		let parts = [];
		const current = GetCookie.Get( 'icwp-wpsf-notbot' );
		let maybeParts = ( ( typeof current === typeof undefined || current === undefined || current === '' ) ? '' : current ).split( 'Z' );
		let expiry = maybeParts.pop();
		if ( expiry ) {
			let regResult = /^exp-([0-9]+)$/.exec( expiry );
			if ( regResult && ( Math.round( Date.now() / 1000 ) < Number( regResult[ 1 ] ) ) ) {
				parts = maybeParts;
			}
		}
		return parts;
	}

	async fetch_NotBot() {
		this.request_count++;

		const reqData = ObjectOps.ObjClone( this._base_data.ajax.not_bot );
		delete reqData.ajaxurl;
		delete reqData._rest_url;
		/** todo: remove after switch to REST */
		delete reqData._wpnonce;

		return fetch( this.shield_ajaxurl, this.constructFetchRequestData( reqData ) )
		.then( raw => raw.text() )
		.then( rawText => {
			const parsed = AjaxParseResponseService.ParseIt( rawText );
			if ( ObjectOps.IsEmpty( parsed ) || !( 'data' in parsed ) ) {
				throw new Error( 'Data in the notbot request could not be parsed.' );
			}
			else if ( ( 'altcha_data' in parsed.data ) && this.verifyAltchaChallengeData( parsed.data.altcha_data ) ) {
				this.notbot_altcha_challenge_request_data = parsed.data.altcha_data;
			}
			else if ( this.isAltchaChallengeRequired() ) {
				throw new Error( 'Could not verify the altcha challenge data in response.' );
			}
			return parsed;
		} )
		.then( () => this.reFire( 0 ) )
		.catch( error => {
			this.failed_request_count++;
			console.log( 'fetch_NotBot() error: ' + error );
		} );
	}

	constructFetchRequestData( core ) {
		return {
			method: 'POST',
			body: ( new URLSearchParams( core ) ).toString(),
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest',
			},
		};
	};

	windowHasHadFocus() {
		return this.window_focus_at > this.window_blur_at || this.window_focus_at > this.start_refire_at;
	}
}