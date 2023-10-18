import { BaseService } from "./BaseService";
import { GetCookie } from "./GetCookie";

export class NotBot extends BaseService {

	init() {
		this.can_send_request = true;
		this.nonce_cook = '';
		this.request_count = 0;
		this.use_fetch = typeof fetch !== typeof undefined;
		this.shield_ajaxurl = this._base_data.ajax.not_bot.ajaxurl;
		delete this._base_data.ajax.not_bot.ajaxurl;

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
		this.readNotBotNonceFromCookie();
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
					this.use_fetch ? this.notBotViaGetNonce() : this.legacyReq();
				}
			}
			window.setTimeout( this.fire, 60000 );
		}
	};

	/** Overcome limitations of page caching by passing latest nonce via cookie **/
	readNotBotNonceFromCookie() {
		this.nonce_cook = GetCookie.Get( 'shield-notbot-nonce' );
		if ( typeof this.nonce_cook !== typeof undefined && this.nonce_cook.length > 0 ) {
			this._base_data.ajax.not_bot.exnonce = this.nonce_cook;
		}
		return this.nonce_cook;
	};

	async notBotViaGetNonce() {
		this.readNotBotNonceFromCookie();

		/** If we don't have a nonce i.e. it's been cleared by a failed attempt **/
		if ( this._base_data.ajax.not_bot.exnonce === '' ) {
			try {
				fetch( this.shield_ajaxurl, this.constructFetchRequestData( this._base_data.ajax.not_bot_nonce ) )
				.then( response => {
					let newNonceCookie = this.readNotBotNonceFromCookie();
					if ( newNonceCookie === '' ) {
						throw new Error( "Can't read new notbot nonce cookie." )
					}
					return response;
				} )
				.then( response => response.json() )
				.then( response_data => {
					if ( response_data ) {
						this.can_send_request = response_data && response_data.success;
						this.notBotSendReqWithFetch();
					}
					else {
						this.use_fetch = false;
					}
					return response_data;
				} )
				.catch( error => {
					console.log( error );
					this.use_fetch = false;
				} );
			}
			catch ( error ) {
				this.use_fetch = false;
				console.log( error );
			}
		}
		else {
			await this.notBotSendReqWithFetch();
		}
	}

	async notBotSendReqWithFetch() {
		this.readNotBotNonceFromCookie();
		try {
			fetch( this.shield_ajaxurl, this.constructFetchRequestData( this._base_data.ajax.not_bot ) )
			.then( response => {
				if ( response.status === 401 ) {
					this._base_data.ajax.not_bot.exnonce = '';
					this.notBotViaGetNonce();
					throw new Error( 'notBotSendReqWithFetch() chain cancelled with failed nonce' );
				}
				return response;
			} )
			.then( response => response.json() )
			.then( response_data => {
				if ( response_data ) {
					this.can_send_request = response_data && response_data.success;
				}
				else {
					this.use_fetch = false;
				}
				return response_data;
			} )
			.catch( error => {
				console.log( 'notBotSendReqWithFetch() error:' );
				console.log( error );
				this.use_fetch = false;
			} );
		}
		catch ( error ) {
			this.use_fetch = false;
			console.log( error );
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