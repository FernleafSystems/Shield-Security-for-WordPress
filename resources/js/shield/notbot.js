if ( typeof Shield_Antibot === typeof undefined && typeof shield_vars_notbotjs !== typeof undefined ) {

	var Shield_Antibot = new function () {

		let request_count = 0;
		let can_continue = true;

		var domReady = function ( fn ) {
			if ( document.readyState !== 'loading' ) {
				fn();
			}
			else if ( document.addEventListener ) {
				document.addEventListener( 'DOMContentLoaded', fn );
			}
			else {
				document.attachEvent( 'onreadystatechange', function () {
					if ( document.readyState !== 'loading' )
						fn();
				} );
			}
		}

		this.initialise = function () {
			/**
			 * @since 11.2 we no longer wait until DOM is ready.
			 * @since 12.0.10 we return to using cookies to optimise whether the AJAX request is sent.
			 * This is mainly AJAX so it's asynchronous and wont hold up any other part of the page load.
			 * Early execution also helps mitigate the case where login requests are
			 * sent quickly, before browser has fired NotBot request.
			 */
			if ( shield_vars_notbotjs.flags.run ) {
				fire();
			}
			/**
			 * @since 11.2 this script is only loaded if a not bot signal doesn't exist for this IP.
			 */
			domReady( function () {
				// fire();
			} );
		};

		/**
		 * @since 12.0.10 - rather than auto send request every page load, check for cookie repeatedly and send if
		 *     absent.
		 */
		var fire = function () {
			let current = getCookie( 'icwp-wpsf-notbot' );
			if ( current === undefined || typeof (current) === 'undefined' ) {
				sendReq();
			}
			if ( can_continue && request_count < 10 ) {
				window.setTimeout( fire, 10000 );
			}
		};

		var sendReq = function ( name ) {
			request_count++;

			let xhr = new XMLHttpRequest();

			/**
			 * Ensures that if there's an error with the AJAX, we don't continue
			 * to keep trying the requests.
			 */
			xhr.onreadystatechange = function () {
				if ( xhr.readyState === 4 ) {
					let resp = JSON.parse( xhr.response );
					can_continue = resp && resp.success;
					if ( !can_continue ) {
						console.log( xhr.response );
					}
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