if ( typeof icwp_wpsf_vars_ipdetect !== 'undefined' ) {
	let iCWP_WPSF_IP_Detect = new function () {
		this.runIpDetect = function () {
			jQuery.get( "https://ipinfo.io", function ( response ) {
				if ( typeof response !== 'undefined' && typeof response[ 'ip' ] !== 'undefined' ) {
					icwp_wpsf_vars_ipdetect.ajax[ 'ip' ] = response[ 'ip' ];
					jQuery.post( ajaxurl, icwp_wpsf_vars_ipdetect.ajax ).always();
				}
			}, "jsonp" );
		};
	}();
	jQuery( document ).ready( function () {
		iCWP_WPSF_IP_Detect.runIpDetect();
	} );
}
