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
 * @typedef {SilentCaptchaRequestData & {
 *   altcha_version?: string|number,
 *   altcha_challenge?: string,
 * }} SilentCaptchaAltchaRequestData
 *
 * @typedef {{ data?: unknown, success?: unknown }} SilentCaptchaAjaxPayload
 * @typedef {{mode: 'cookie'|'cookie_free', required: Array<'notbot'|'altcha'>, exchange_valid: boolean}} SilentCaptchaResponseState
 * @typedef {{data: Record<string, any>, state: SilentCaptchaResponseState|null, modeChanged: boolean}} SilentCaptchaExchange
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
		this.config = this._base_data?.config;
		this.mode = this.config?.mode === 'cookie_free' ? 'cookie_free' : 'cookie';
		this.completedAt = 0;
		this.failedAt = 0;
		this.inFlight = false;
		this.diagnosticConsumed = false;
		this.timer = 0;
		this.formSeen = false;

		/** @type {SilentCaptchaRequestData|null} */
		this.silentCaptchaAjaxData = this.resolveSilentCaptchaAjaxData();
		this.shield_ajaxurl = this.silentCaptchaAjaxData?.ajaxurl || '';

		super.init();
	}

	canRun() {
		return typeof this.shield_ajaxurl === 'string' && this.shield_ajaxurl.length > 0;
	}

	run() {
		if ( this.mode === 'cookie' ) this.clearFreshness();
		this.observeForms();
		window.addEventListener( 'focus', () => {
			this.window_focus_at = Date.now();
			if ( this.mode === 'cookie_free' ) this.evaluate();
		} );
		window.addEventListener( 'blur', () => {
			this.window_blur_at = Date.now();
		} );

		for ( const event of [ 'pageshow', 'storage' ] ) {
			window.addEventListener( event, () => {
				if ( this.mode === 'cookie_free' ) this.evaluate();
			} );
		}
		document.addEventListener( 'visibilitychange', () => {
			if ( this.mode === 'cookie_free' ) this.evaluate();
		} );
		this.fire();
	};

	fire() {
		if ( this.mode === 'cookie_free' ) {
			this.evaluate();
			return;
		}
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
				this.inFlight = true;
				this.solveAndSubmitAltcha()
				.then( result => { if ( !result.modeChanged ) this.reFire(); } )
				.catch( () => { this.failed_request_count++; } )
				.finally( () => {
					this.altchaChallengeRequestData = null;
					this.inFlight = false;
					if ( this.mode === 'cookie_free' ) this.evaluate();
				} );
			}
			else {
				this.fetchSilentCaptcha();
			}
		}
		else if ( this.isBasicSignalRequired() ) {
			this.fetchSilentCaptcha();
		}
		else {
			this.reFire();
		}
	}

	async solveAndSubmitAltcha() {
		this.request_count++;
		const solution = await solveChallenge( {
			challenge: this.parseAltchaChallenge( this.altchaChallengeRequestData ), deriveKey,
		} );
		if ( solution === null ) throw new Error( 'ALTCHA v2 challenge could not be solved.' );
		const reqData = /** @type {SilentCaptchaAltchaRequestData} */ ( ObjectOps.ObjClone( this.altchaChallengeRequestData ) );
		reqData.altcha_solution = JSON.stringify( solution );
		return this.fetchExchange( reqData );
	}

	reFire( reFireTimeout = 15000 ) {
		this.start_refire_at = Date.now();
		window.clearTimeout( this.timer );
		this.timer = window.setTimeout( () => {

			if ( reFireTimeout === 0 || this.windowHasHadFocus() ) {
				this.fire();
			}
			else {
				this.reFire( 2500 );
			}

		}, reFireTimeout );
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
		const startedMode = this.mode;
		this.inFlight = true;
		try {
			if ( this.silentCaptchaAjaxData === null ) throw new Error( 'Missing request data.' );
			const result = await this.fetchExchange( this.silentCaptchaAjaxData );
			if ( result.modeChanged ) return;
			if ( this.mode === 'cookie_free' ) {
				await this.consumeCookieFreeResponse( result );
			}
			else if ( this.verifyAltchaChallengeData( result.data.altcha_data ) ) {
				this.altchaChallengeRequestData = result.data.altcha_data;
				this.reFire( 0 );
			}
			else if ( !this.altchaUnsupported && this.isCookieSignalRequired( 'altcha' ) ) {
				throw new Error( 'Could not verify the altcha challenge data in response.' );
			}
			else {
				this.reFire();
			}
		}
		catch {
			this.failed_request_count++;
			if ( this.mode === 'cookie_free' ) this.failedAt = Date.now();
		}
		finally {
			this.inFlight = false;
			if ( startedMode === 'cookie_free' || this.mode !== startedMode ) {
				this.altchaChallengeRequestData = null;
				this.fire();
			}
		}
	}

	/**
	 * @param {SilentCaptchaRequestData} requestData
	 * @returns {Promise<SilentCaptchaExchange>}
	 */
	async fetchExchange( requestData ) {
		this.request_count++;
		const reqData = /** @type {SilentCaptchaRequestData} */ ( ObjectOps.ObjClone( requestData ) );
		delete reqData.ajaxurl;
		delete reqData._rest_url;
		delete reqData._wpnonce;
		const response = await fetch( this.shield_ajaxurl, this.constructFetchRequestData( reqData ) );
		const parsed = /** @type {SilentCaptchaAjaxPayload} */ ( AjaxParseResponseService.ParseIt( await response.text() ) );
		const data = this.resolveAjaxPayloadData( parsed );
		if ( data === null ) throw new Error( 'Invalid silentCAPTCHA response.' );
		const state = response.ok && parsed.success === true ? this.parseResponseState( data.notbot_state ) : null;
		const result = { data, state, modeChanged: false };
		if ( state !== null && state.mode !== this.mode ) {
			window.clearTimeout( this.timer );
			this.mode = state.mode;
			result.modeChanged = true;
			if ( this.mode === 'cookie' ) this.clearFreshness();
			if ( !state.exchange_valid ) this.failedAt = Date.now();
		}
		return result;
	}

	/** @param {SilentCaptchaExchange} result */
	async consumeCookieFreeResponse( result ) {
		if ( result.modeChanged ) return;
		const state = result.state;
		if ( state === null || state.mode !== this.mode || !state.exchange_valid ) {
			throw new Error( 'Invalid silentCAPTCHA exchange.' );
		}
		if ( state.required.length === 0 ) {
			this.completedAt = Date.now();
			this.failedAt = 0;
			try {
				window.localStorage.setItem( this.config.storage_key, JSON.stringify( { completed_at: this.completedAt } ) );
			}
			catch { /* Keep the successful timestamp in this document. */ }
		}
		else {
			this.altchaChallengeRequestData = result.data.altcha_data;
			if ( state.required.includes( 'notbot' ) || !this.hasAltchaChallengeData()
				|| !this.canSolveAltchaChallenge() || this.request_count >= 9 || this.failed_request_count >= 5 ) {
				throw new Error( 'Pending silentCAPTCHA checks cannot be completed.' );
			}
			await this.consumeCookieFreeResponse( await this.solveAndSubmitAltcha() );
		}
	}

	/**
	 * @param {any} state Untrusted AJAX state, validated once at the exchange boundary.
	 * @returns {SilentCaptchaResponseState|null}
	 */
	parseResponseState( state ) {
		return state !== null && typeof state === 'object'
			&& [ 'cookie', 'cookie_free' ].includes( state.mode )
			&& typeof state.exchange_valid === 'boolean' && Array.isArray( state.required )
			&& state.required.every( signal => signal === 'notbot' || signal === 'altcha' ) ? state : null;
	}

	validConfig() {
		const timing = this.config?.refresh_seconds;
		return timing && [ timing.ordinary, timing.form, timing.login ].every( value =>
			typeof value === 'number' && Number.isFinite( value ) && value > 0 )
			&& timing.login <= timing.form && timing.form <= timing.ordinary
			&& typeof this.config.storage_key === 'string' && this.config.storage_key.length > 0
			&& typeof this.config.is_login === 'boolean';
	}

	clearFreshness() {
		this.completedAt = 0;
		try {
			if ( typeof this.config?.storage_key === 'string' ) window.localStorage.removeItem( this.config.storage_key );
		}
		catch { /* Cookie mode never uses the stored timestamp. */ }
	}

	readCompletion() {
		let stored = 0;
		try { stored = JSON.parse( window.localStorage.getItem( this.config.storage_key ) )?.completed_at; }
		catch { /* Storage is optional. */ }
		const usable = value => typeof value === 'number' && Number.isFinite( value ) && value > 0 && value <= Date.now();
		this.completedAt = Math.max( usable( stored ) ? stored : 0, usable( this.completedAt ) ? this.completedAt : 0 );
		return this.completedAt;
	}

	evaluate() {
		window.clearTimeout( this.timer );
		if ( !this.validConfig() || document.visibilityState !== 'visible' || this.inFlight ) return;
		const timing = this.config.refresh_seconds;
		const windowMs = ( this.config.is_login ? timing.login : this.formSeen ? timing.form : timing.ordinary ) * 1000;
		const completed = this.readCompletion();
		const now = Date.now();
		const cooldown = this.failedAt ? this.failedAt + windowMs - now : 0;
		const diagnostic = this.isForceNotbotRequested() && !this.diagnosticConsumed;
		const remaining = cooldown > 0 ? cooldown : diagnostic || !completed ? 0 : completed + windowMs - now;
		if ( remaining > 0 ) {
			this.timer = window.setTimeout( () => this.evaluate(), remaining );
		}
		else {
			this.inFlight = true;
			if ( diagnostic ) this.diagnosticConsumed = true;
			this.request_count = 0;
			this.failed_request_count = 0;
			this.fetchSilentCaptcha();
		}
	}

	observeForms() {
		if ( this.config?.is_login ) return;
		if ( document.querySelector( 'form' ) ) {
			this.formSeen = true;
			return;
		}
		const observer = new window.MutationObserver( records => {
			for ( const record of records ) {
				for ( const node of record.addedNodes ) {
					if ( node instanceof Element && ( node.matches( 'form' ) || node.querySelector( 'form' ) ) ) {
						this.formSeen = true;
						observer.disconnect();
						if ( this.mode === 'cookie_free' ) this.evaluate();
						return;
					}
				}
			}
		} );
		observer.observe( document, { childList: true, subtree: true } );
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
