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
 * @typedef {{ data?: unknown }} SilentCaptchaAjaxPayload
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

		/** @type {SilentCaptchaRequestData|null} */
		this.silentCaptchaAjaxData = this.resolveSilentCaptchaAjaxData();
		this.shield_ajaxurl = this.silentCaptchaAjaxData?.ajaxurl || '';

		super.init();
	}

	canRun() {
		return typeof this.shield_ajaxurl === 'string' && this.shield_ajaxurl.length > 0;
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
						 const parsed = /** @type {SilentCaptchaAjaxPayload} */ ( AjaxParseResponseService.ParseIt( rawText ) );
						 if ( this.resolveAjaxPayloadData( parsed ) === null ) {
							 throw new Error( 'Data in the altcha request could not be parsed.' );
						 }
						 return rawText;
					 } )
					 .then( () => this.reFire() );
				 } )
				 .catch( () => {
					 this.failed_request_count++;
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
				this.fire();
			}
			else {
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
		try {
			return !!( window.crypto && window.crypto.subtle );
		}
		catch {
			return false;
		}
	}

	isBasicSignalRequired() {
		return this.isForceNotbotRequested() || this.isCookieSignalRequired( 'notbot' );
	}

	isAltchaChallengeRequired() {
		return !this.altchaUnsupported
			   && ( this.isForceNotbotRequested() || this.isCookieSignalRequired( 'altcha' ) );
	}

	isCookieSignalRequired( signal ) {
		return !this.getNonRequiredFlagsFromCookie().includes( signal );
	}

	isForceNotbotRequested() {
		try {
			return PageQueryParam.Retrieve( 'force_notbot' ) === '1';
		}
		catch {
			return false;
		}
	}

	/**
	 * @returns {SilentCaptchaRequestData|null}
	 */
	resolveSilentCaptchaAjaxData() {
		try {
			const requestData = this._base_data?.ajax?.silentcaptcha;
			if ( requestData === null || typeof requestData !== 'object' || Array.isArray( requestData ) ) {
				return null;
			}
			if ( typeof requestData.ajaxurl !== 'string' || requestData.ajaxurl.length < 1 ) {
				return null;
			}

			return /** @type {SilentCaptchaRequestData} */ ( requestData );
		}
		catch {
			return null;
		}
	}

	/**
	 * We now include the expiry of the cookie within the cookie itself. This is because Chrome doesn't update the
	 * cookie data to account for cookie expiration within the same page load. So we must provide a mechanism for
	 * informing the script that the known cookie status has expired.
	 */
	getNonRequiredFlagsFromCookie() {
		try {
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
		catch {
			return [];
		}
	}

	/**
	 * @param {SilentCaptchaAjaxPayload} parsed
	 * @returns {Record<string, any>|null}
	 */
	resolveAjaxPayloadData( parsed ) {
		if ( parsed === null || typeof parsed !== 'object' || Array.isArray( parsed ) ) {
			return null;
		}
		if ( !Object.prototype.hasOwnProperty.call( parsed, 'data' ) ) {
			return null;
		}

		const data = parsed.data;
		return data !== null && typeof data === 'object' && !Array.isArray( data ) ? /** @type {Record<string, any>} */ ( data ) : null;
	}

	async fetchSilentCaptcha() {
		this.request_count++;

		let reqData = {};
		try {
			if ( this.silentCaptchaAjaxData === null ) {
				throw new Error( 'silentCAPTCHA request data is unavailable.' );
			}
			reqData = /** @type {SilentCaptchaRequestData} */ ( ObjectOps.ObjClone( this.silentCaptchaAjaxData ) );
		}
		catch {
			this.failed_request_count++;
			return null;
		}
		delete reqData.ajaxurl;
		delete reqData._rest_url;
		/** todo: remove after switch to REST */
		delete reqData._wpnonce;

		return fetch( this.shield_ajaxurl, this.constructFetchRequestData( reqData ) )
		.then( raw => raw.text() )
		.then( rawText => {
			const parsed = /** @type {SilentCaptchaAjaxPayload} */ ( AjaxParseResponseService.ParseIt( rawText ) );
			const data = this.resolveAjaxPayloadData( parsed );
			if ( data === null ) {
				throw new Error( 'Data in the silentCAPTCHA request could not be parsed.' );
			}
			else if ( this.verifyAltchaChallengeData( data.altcha_data ) ) {
				this.altchaChallengeRequestData = data.altcha_data;
				this.reFire( 0 );
			}
			else if ( !this.altchaUnsupported && this.isCookieSignalRequired( 'altcha' ) ) {
				throw new Error( 'Could not verify the altcha challenge data in response.' );
			}
			else {
				this.reFire();
			}
			return parsed;
		} )
		.catch( () => {
			this.failed_request_count++;
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
