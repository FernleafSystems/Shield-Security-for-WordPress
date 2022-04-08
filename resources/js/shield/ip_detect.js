if ( typeof icwp_wpsf_vars_ipdetect !== 'undefined' ) {
	jQuery( document ).ready( function () {
		jQuery.getJSON( icwp_wpsf_vars_ipdetect.url, function ( response ) {
			if ( typeof response !== 'undefined' && typeof response[ 'ip' ] !== 'undefined' ) {
				icwp_wpsf_vars_ipdetect.ajax[ 'ip' ] = response[ 'ip' ];
				jQuery.post( ajaxurl, icwp_wpsf_vars_ipdetect.ajax ).always();
			}
		} );
	} );
}
