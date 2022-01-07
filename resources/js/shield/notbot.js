/**
 * @var shield_vars_notbotjs object
 */
if ( typeof Shield_Antibot === typeof undefined && typeof shield_vars_notbotjs !== typeof undefined ) {
	var Shield_Antibot = new function () {

		let request_count = 0;
		let can_send_request = true;

		this.initialise = function () {
			/**
			 * @since 11.2 we no longer wait until DOM is ready.
			 * @since 12.0.10 we return to using cookies to optimise whether the AJAX request is sent.
			 * This is mainly AJAX so it's asynchronous and won't hold up any other part of the page load.
			 * Early execution also helps mitigate the case where login requests are
			 * sent quickly, before browser has fired NotBot request.
			 */
			if ( shield_vars_notbotjs.flags.run ) {
				fire();
			}
		};

		/**
		 * @since 12.0.10 - rather than auto send request every page load, check for cookie repeatedly and send if
		 *     absent.
		 */
		var fire = function () {
			if ( can_send_request && request_count < 10 ) {
				let current = getCookie( 'icwp-wpsf-notbot' );
				if ( current === undefined || typeof (current) === 'undefined' ) {
					sendReq();
				}
			}
			window.setTimeout( fire, 30000 );
		};

		/**
		 * We use the cookie to help ensure we don't send unnecessary requests and keep checking
		 */
		var sendReq = function () {
			request_count++;

			let xhr = new XMLHttpRequest();

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

			xhr.open( "POST", shield_vars_notbotjs.hrefs.ajax, true );
			xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded;' );
			xhr.send( shield_vars_notbotjs.ajax.not_bot );
		};

		var getCookie = function ( name ) {
			var value = "; " + document.cookie;
			var parts = value.split( "; " + name + "=" );
			if ( parts.length === 2 ) return parts.pop().split( ";" ).shift();
		};
	}();

	Shield_Antibot.initialise();
}