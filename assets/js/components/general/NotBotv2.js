import { solveChallenge } from 'altcha-lib';
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxParseResponseService } from "../services/AjaxParseResponseService";
import { GetCookie } from "../../util/GetCookie";
import { ObjectOps } from "../../util/ObjectOps";
import { PageQueryParam } from "../../util/PageQueryParam";

export class NotBotv2 extends BaseAutoExecComponent {

	init() {
		this.notbot_altcha_challenge_request_data = null;
		this.altcha_solution = null;

		this.request_count = 0;
		this.failed_request_count = 0;

		this.shield_ajaxurl = this._base_data.ajax.not_bot.ajaxurl;

		super.init();
	}

	/**
	 * @since 11.2 we no longer wait until DOM is ready.
	 * @since 12.0.10 we return to using cookies to optimise whether the AJAX request is sent.
	 * This is AJAX, so it's asynchronous and won't hold up any other part of the page load.
	 * Early execution also helps mitigate the case where login requests are
	 * sent quickly, before browser has fired NotBot request.
	 * @since 12.0.10 - rather than auto send each page load, check for cookie repeatedly and send if absent.
	 */
	run() {
		this.fire();
	};

	fire() {
		if ( !this._base_data.flags.skip && this.request_count < 10 && this.failed_request_count < 5 ) {
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
					 } );
				 } );
			}
			else {
				this.performPathNotbot().finally();
			}
		}
		else {
			this.reFire();
		}
	}

	reFire( timeout = 120000 ) {
		window.setTimeout( () => this.fire(), timeout );
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

	getNonRequiredFlagsFromCookie() {
		const current = GetCookie.Get( 'icwp-wpsf-notbot' );
		return ( ( typeof current === typeof undefined || current === undefined || current === '' ) ? '' : current ).split( 'Z' );
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
			else {
				throw new Error( 'Could not verify the altcha challenge data.' );
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
}