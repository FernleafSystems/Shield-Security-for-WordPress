import { BaseComponent } from "../BaseComponent";
import { AjaxParseResponseService } from "../services/AjaxParseResponseService";
import { GetCookie } from "../../util/GetCookie";
import { ObjectOps } from "../../util/ObjectOps";

export class NotBot extends BaseComponent {

	init() {
		this.can_send_request = true;
		this.request_count = 0;
		this.use_fetch = typeof fetch !== typeof undefined;
		this.shield_ajaxurl = this._base_data.ajax.not_bot.ajaxurl;
		delete this._base_data.ajax.not_bot.ajaxurl;
		delete this._base_data.ajax.not_bot._rest_url;
		/** todo: remove after switch to REST */
		delete this._base_data.ajax.not_bot._wpnonce;

		this.exec();
	}

	canRun() {
		return this._base_data.flags.run;
	}

	/**
	 * @since 11.2 we no longer wait until DOM is ready.
	 * @since 12.0.10 we return to using cookies to optimise whether the AJAX request is sent.
	 * This is AJAX, so it's asynchronous and won't hold up any other part of the page load.
	 * Early execution also helps mitigate the case where login requests are
	 * sent quickly, before browser has fired NotBot request.
	 */
	run() {
		this.fire();
	};

	/**
	 * @since 12.0.10 - rather than auto send each page load, check for cookie repeatedly and send if absent.
	 */
	fire() {
		if ( this.request_count < 10 ) {
			if ( this.can_send_request ) {
				let current = GetCookie.Get( 'icwp-wpsf-notbot' );
				if ( typeof current === typeof undefined || current === undefined || current === '' ) {
					this.request_count++;
					this.use_fetch ? this.runNotBot() : this.legacyReq();
				}
			}
			window.setTimeout( () => this.fire(), 10000 );
		}
	};

	/** Overcome limitations of page caching by passing latest nonce via cookie **/
	readNotBotNonceFromCookie() {
		let nonce_cook = GetCookie.Get( 'shield-notbot-nonce' );
		if ( typeof nonce_cook === typeof undefined || nonce_cook.length === 0 ) {
			nonce_cook = '';
		}
		if ( nonce_cook.length > 0 ) {
			this.setNotBotNonce( nonce_cook );
		}
		return nonce_cook;
	};

	setNotBotNonce( nonce ) {
		this._base_data.ajax.not_bot.exnonce = nonce;
	}

	getNotBotNonce() {
		this.readNotBotNonceFromCookie();
		return this._base_data.ajax.not_bot.exnonce;
	}

	async runNotBot() {
		/** If we don't have a nonce i.e. it's been cleared by a failed attempt **/
		await this.fetch_RetrieveNotBotNonce()
				  .then( () => this.fetch_NotBot() );
	}

	async fetch_RetrieveNotBotNonce() {
		if ( this.getNotBotNonce().length === 0 ) {
			fetch( this.shield_ajaxurl, this.constructFetchRequestData( this._base_data.ajax.not_bot_nonce ) )
			.then( raw => raw.text() )
			.then( rawText => {
				const json = AjaxParseResponseService.ParseIt( rawText );
				this.can_send_request = !ObjectOps.IsEmpty( json );
				if ( this.getNotBotNonce().length === 0 ) {
					this.setNotBotNonce( json.data.nonce );
				}
				return rawText;
			} )
			.catch( error => console.log( error ) );
		}
	}

	async fetch_NotBot() {
		if ( this.getNotBotNonce().length > 0 ) {
			fetch( this.shield_ajaxurl, this.constructFetchRequestData( this._base_data.ajax.not_bot ) )
			.then( raw => {
				if ( raw.status === 401 ) {
					this.setNotBotNonce( '' );
				}
				return raw;
			} )
			.then( raw => raw.text() )
			.then( rawText => {
				this.can_send_request = !ObjectOps.IsEmpty( AjaxParseResponseService.ParseIt( rawText ) );
				return rawText;
			} )
			.catch( error => {
				console.log( 'notBotSendReqWithFetch() error: ' + error );
			} );
		}
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

	legacyReq() {
		let xhr = new XMLHttpRequest();
		xhr.open( "POST", this.shield_ajaxurl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		xhr.setRequestHeader( 'X-Requested-With', 'XMLHttpRequest' );

		/**
		 * Ensures that if there's an error with the AJAX, we don't keep retrying the requests.
		 */
		xhr.onreadystatechange = () => {
			if ( xhr.readyState === 4 ) {
				let rawResp = xhr.response;
				if ( rawResp != null && rawResp !== '' && rawResp.charAt( 0 ) === '{' ) {
					let resp = JSON.parse( rawResp )
					this.can_send_request = resp && resp.success;
					if ( !this.can_send_request ) {
						console.log( xhr.response );
					}
				}
			}
			else {
				this.can_send_request = false;
			}
		}

		xhr.send( ( new URLSearchParams( this._base_data.ajax.not_bot ) ).toString() );
	};
}