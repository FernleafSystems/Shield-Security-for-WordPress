/**
 * @var shield_vars_notbotjs object
 */
if ( typeof Shield_Antibot === typeof undefined && typeof shield_vars_notbotjs !== typeof undefined ) {

	let shield_ajaxurl = shield_vars_notbotjs.vars.ajaxurl;
	delete shield_vars_notbotjs.vars.ajaxurl;

	let Shield_Antibot = new function () {

		let request_count = 0;
		let can_send_request = true;
		let nonce_cook = '';
		let use_fetch = typeof fetch !== typeof undefined;

		this.initialise = function () {
			/**
			 * @since 11.2 we no longer wait until DOM is ready.
			 * @since 12.0.10 we return to using cookies to optimise whether the AJAX request is sent.
			 * This is AJAX, so it's asynchronous and won't hold up any other part of the page load.
			 * Early execution also helps mitigate the case where login requests are
			 * sent quickly, before browser has fired NotBot request.
			 */
			if ( shield_vars_notbotjs.flags.run ) {
				readNotBotNonceFromCookie();
				fire();
			}
		};

		/**
		 * @since 12.0.10 - rather than auto send each page load, check for cookie repeatedly and send if absent.
		 */
		let fire = function () {
			if ( request_count < 10 ) {
				if ( can_send_request ) {
					let current = getCookie( 'icwp-wpsf-notbot' );
					if ( typeof current === typeof undefined || current === undefined || current === '' ) {
						request_count++;
						use_fetch ? notBotViaGetNonce() : legacyReq();
					}
				}
				window.setTimeout( fire, 60000 );
			}
		};

		/** Overcome limitations of page caching by passing latest nonce via cookie **/
		let readNotBotNonceFromCookie = function () {
			nonce_cook = getCookie( 'shield-notbot-nonce' );
			if ( typeof nonce_cook !== typeof undefined && nonce_cook.length > 0 ) {
				shield_vars_notbotjs.ajax.not_bot.exnonce = nonce_cook;
			}
			return nonce_cook;
		};

		async function notBotViaGetNonce() {
			readNotBotNonceFromCookie();

			/** If we don't have a nonce i.e. it's been cleared by a failed attempt **/
			if ( shield_vars_notbotjs.ajax.not_bot.exnonce === '' ) {
				try {
					fetch( shield_ajaxurl, constructFetchRequestData( shield_vars_notbotjs.ajax.not_bot_nonce ) )
					.then( response => {
						let newNonceCookie = readNotBotNonceFromCookie();
						if ( newNonceCookie === '' ) {
							throw new Error( "Can't read new notbot nonce cookie." )
						}
						return response;
					} )
					.then( response => response.json() )
					.then( response_data => {
						if ( response_data ) {
							can_send_request = response_data && response_data.success;
							notBotSendReqWithFetch();
						}
						else {
							use_fetch = false;
						}
						return response_data;
					} )
					.catch( error => {
						console.log( error );
						use_fetch = false;
					} );
				}
				catch ( error ) {
					use_fetch = false;
					console.log( error );
				}
			}
			else {
				await notBotSendReqWithFetch();
			}
		}

		async function notBotSendReqWithFetch() {
			readNotBotNonceFromCookie();
			try {
				fetch( shield_ajaxurl, constructFetchRequestData( shield_vars_notbotjs.ajax.not_bot ) )
				.then( response => {
					if ( response.status === 401 ) {
						shield_vars_notbotjs.ajax.not_bot.exnonce = '';
						notBotViaGetNonce();
						throw new Error( 'notBotSendReqWithFetch() chain cancelled with failed nonce' );
					}
					return response;
				} )
				.then( response => response.json() )
				.then( response_data => {
					if ( response_data ) {
						can_send_request = response_data && response_data.success;
					}
					else {
						use_fetch = false;
					}
					return response_data;
				} )
				.catch( error => {
					console.log( 'notBotSendReqWithFetch() error:' );
					console.log( error );
					use_fetch = false;
				} );
			}
			catch ( error ) {
				use_fetch = false;
				console.log( error );
			}
		}

		let getCookie = function ( name ) {
			let parts = ("; " + document.cookie).split( "; " + name + "=" );
			return parts.length === 2 ? parts.pop().split( ";" ).shift() : '';
		};

		let constructFetchRequestData = function ( core ) {
			return {
				method: 'POST',
				body: (new URLSearchParams( core )).toString(),
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest',
				},
			};
		};
	}();

	let legacyReq = function () {
		let xhr = new XMLHttpRequest();
		xhr.open( "POST", shield_ajaxurl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		xhr.setRequestHeader( 'X-Requested-With', 'XMLHttpRequest' );

		/**
		 * Ensures that if there's an error with the AJAX, we don't keep retrying the requests.
		 */
		xhr.onreadystatechange = function () {
			if ( xhr.readyState === 4 ) {
				let rawResp = xhr.response;
				if ( rawResp != null && rawResp !== '' && rawResp.charAt( 0 ) === '{' ) {
					let resp = JSON.parse( rawResp )
					can_send_request = resp && resp.success;
					if ( !can_send_request ) {
						console.log( xhr.response );
					}
				}
			}
			else {
				can_send_request = false;
			}
		}

		xhr.send( (new URLSearchParams( shield_vars_notbotjs.ajax.not_bot )).toString() );
	};

	Shield_Antibot.initialise();
}