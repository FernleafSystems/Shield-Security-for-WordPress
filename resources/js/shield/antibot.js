if ( typeof Shield_Antibot === typeof undefined && typeof shield_vars_antibotjs !== typeof undefined ) {

	var Shield_Antibot = new function () {

		var request_count = 0;

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
			domReady( function () {
				fire();
			} );
		};

		var fire = function () {
			var sendRequest = false;
			var current = getCookie( 'icwp-wpsf-notbot' );
			if ( current === undefined ) {
				sendRequest = true;
			}
			else {
				var remaining = current.split( "z" )[ 0 ] - Math.floor( Date.now() / 1000 );
				if ( remaining < 60 ) {
					sendRequest = true;
				}
			}

			if ( sendRequest && request_count < 11 ) {
				sendReq();
			}
			window.setTimeout( fire, 60000 );
		};

		var sendReq = function ( name ) {
			var xhttp = new XMLHttpRequest();
			xhttp.open( "POST", shield_vars_antibotjs.hrefs.ajax, true );
			xhttp.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded;' );
			xhttp.send( shield_vars_antibotjs.ajax.not_bot );
			request_count++;
		};

		var getCookie = function ( name ) {
			var value = "; " + document.cookie;
			var parts = value.split( "; " + name + "=" );
			if ( parts.length === 2 ) return parts.pop().split( ";" ).shift();
		};
	}();
	Shield_Antibot.initialise();
}