import { solveChallenge } from 'altcha-lib';
import { deriveKey } from 'altcha-lib/algorithms/web/pbkdf2';
import { AjaxParseResponseService } from "../services/AjaxParseResponseService";
import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { GetCookie } from "../../util/GetCookie";
import { ObjectOps } from "../../util/ObjectOps";
import { PageQueryParam } from "../../util/PageQueryParam";

/**
 * @typedef {Record<string, any> & {
 *   ajaxurl?: string,
 *   _wpnonce?: string,
 *   _rest_url?: string,
 *   altcha_solution?: string
 * }} SilentCaptchaRequestData
 *
 * @typedef {Record<string, any> & {
 *   altcha_version?: string|number,
 *   altcha_challenge?: string,
 *   ajaxurl?: string,
 *   _wpnonce?: string,
 *   _rest_url?: string,
 *   altcha_solution?: string
 * }} SilentCaptchaAltchaRequestData
 *
 * @typedef {{ data?: { altcha_data?: SilentCaptchaAltchaRequestData } }} SilentCaptchaAjaxPayload
 */

export class SilentCaptcha extends BaseAutoExecComponent {

	init() {
		this.window_focus_at = Date.now();
		this.window_blur_at = 0;

		/** @type {SilentCaptchaAltchaRequestData|null} */
		this.altchaChallengeRequestData = null;
		this.altchaUnsupported = false;

		this.request_count = 0;
		this.failed_request_count = 0;

		this.shield_ajaxurl = this._base_data.ajax.silentcaptcha.ajaxurl;

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
				if ( !this.canSolveAltchaChallenge() ) {
					this.altchaUnsupported = true;
					this.altchaChallengeRequestData = null;
					this.reFire();
					return;
				}

				this.request_count++;
				solveChallenge( {
					challenge: this.parseAltchaChallenge( this.altchaChallengeRequestData ),
					deriveKey,
				} )
				 .then( solution => {
					 if ( solution === null ) {
						 throw new Error( 'ALTCHA v2 challenge could not be solved.' );
					 }
					 this.request_count++;

					 const reqData = /** @type {SilentCaptchaAltchaRequestData} */ ( ObjectOps.ObjClone( this.altchaChallengeRequestData ) );
					 reqData.altcha_solution = JSON.stringify( solution );
					 delete reqData.ajaxurl;
					 delete reqData._wpnonce;
					 delete reqData._rest_url;

					 return fetch( this.shield_ajaxurl, this.constructFetchRequestData( reqData ) )
					 .then( raw => raw.text() )
					 .then( rawText => {
						 if ( ObjectOps.IsEmpty( AjaxParseResponseService.ParseIt( rawText ) ) ) {
							 throw new Error( 'Data in the altcha request could not be parsed.' );
						 }
						 return rawText;
					 } )
					 .then( () => this.reFire() );
				 } )
				 .catch( error => {
					 this.failed_request_count++;
					 console.log( 'hasAltchaChallengeData() error: ' + error );
				 } )
				 .finally( () => {
					 this.altchaChallengeRequestData = null;
				 } );
			}
			else {
				this.performPathBasicSignal().finally();
			}
		}
		else if ( this.isBasicSignalRequired() ) {
			this.performPathBasicSignal().finally();
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

	async performPathBasicSignal() {
		return this.fetchSilentCaptcha();
	}

	hasAltchaChallengeData() {
		return this.verifyAltchaChallengeData( this.altchaChallengeRequestData );
	}

	/**
	 * @param {SilentCaptchaAltchaRequestData|null} altcha
	 */
	verifyAltchaChallengeData( altcha ) {
		return this.parseAltchaChallenge( altcha ) !== null;
	}

	/**
	 * @param {SilentCaptchaAltchaRequestData|null} altcha
	 * @returns {object|null}
	 */
	parseAltchaChallenge( altcha ) {
		if ( altcha === null || typeof altcha !== 'object' ) {
			return null;
		}
		if ( String( altcha.altcha_version || '' ) !== '2' || typeof altcha.altcha_challenge !== 'string' ) {
			return null;
		}

		try {
			const challenge = JSON.parse( altcha.altcha_challenge );
			const parameters = challenge?.parameters || {};
			const hasRequired = (
				parameters.algorithm === 'PBKDF2/SHA-256'
				&& typeof parameters.nonce === 'string'
				&& typeof parameters.salt === 'string'
				&& typeof parameters.keyPrefix === 'string'
				&& typeof parameters.keySignature === 'string'
				&& typeof parameters.cost === 'number'
				&& typeof parameters.expiresAt === 'number'
				&& typeof parameters.keyLength === 'number'
				&& typeof challenge.signature === 'string'
			);
			if ( !hasRequired ) {
				return null;
			}
			if ( Math.round( Date.now() / 1000 ) >= Number( parameters.expiresAt ) ) {
				return null;
			}
			return challenge;
		}
		catch {
			return null;
		}
	}

	canSolveAltchaChallenge() {
		return !!( window.crypto && window.crypto.subtle );
	}

	isBasicSignalRequired() {
		return PageQueryParam.Retrieve( 'force_notbot' ) === '1' || !this.getNonRequiredFlagsFromCookie().includes( 'notbot' );
	}

	isAltchaChallengeRequired() {
		return !this.altchaUnsupported
			   && ( PageQueryParam.Retrieve( 'force_notbot' ) === '1' || !this.getNonRequiredFlagsFromCookie().includes( 'altcha' ) );
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

	async fetchSilentCaptcha() {
		this.request_count++;

		const reqData = /** @type {SilentCaptchaRequestData} */ ( ObjectOps.ObjClone( this._base_data.ajax.silentcaptcha ) );
		delete reqData.ajaxurl;
		delete reqData._rest_url;
		/** todo: remove after switch to REST */
		delete reqData._wpnonce;

		return fetch( this.shield_ajaxurl, this.constructFetchRequestData( reqData ) )
		.then( raw => raw.text() )
		.then( rawText => {
			const parsed = /** @type {SilentCaptchaAjaxPayload} */ ( AjaxParseResponseService.ParseIt( rawText ) );
			if ( ObjectOps.IsEmpty( parsed ) || !( 'data' in parsed ) ) {
				throw new Error( 'Data in the silentCAPTCHA request could not be parsed.' );
			}
			else if ( ( 'altcha_data' in parsed.data ) && this.verifyAltchaChallengeData( parsed.data.altcha_data ) ) {
				this.altchaChallengeRequestData = parsed.data.altcha_data;
			}
			else if ( this.isAltchaChallengeRequired() ) {
				throw new Error( 'Could not verify the altcha challenge data in response.' );
			}
			return parsed;
		} )
		.then( () => this.reFire( 0 ) )
		.catch( error => {
			this.failed_request_count++;
			console.log( 'fetchSilentCaptcha() error: ' + error );
		} );
	}

	/**
	 * @param {Record<string, any>} core
	 */
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
