if ( typeof icwp_wpsf_vars_ipdetect !== typeof undefined ) {
	jQuery( document ).ready( function () {
		jQuery.getJSON( icwp_wpsf_vars_ipdetect.url, function ( response ) {
			if ( typeof response !== 'undefined' && typeof response[ 'ip' ] !== 'undefined' ) {
				icwp_wpsf_vars_ipdetect.ajax[ 'ip' ] = response[ 'ip' ];
				jQuery.ajax(
					{
						type: "POST",
						url: ajaxurl,
						data: icwp_wpsf_vars_ipdetect.ajax,
						dataType: "text",
						success: function ( raw ) {
							let response = iCWP_WPSF_ParseAjaxResponse.parseIt( raw );
							if ( response.success ) {
								let msg = icwp_wpsf_vars_ipdetect.strings.source_found
									+ ' ' + icwp_wpsf_vars_ipdetect.strings.ip_source
									+ ': ' + response.data.ip_source;
								if ( !icwp_wpsf_vars_ipdetect.flags.silent ) {
									alert( msg );
								}
								console.log( msg );
							}
						}
					}
				).always( function () {
				} );
			}
		} );
	} );
}