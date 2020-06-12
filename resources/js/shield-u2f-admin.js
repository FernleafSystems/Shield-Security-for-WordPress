if ( typeof icwp_wpsf_vars_u2f !== 'undefined' ) {
	jQuery( document ).ready( function () {

		let $oBtnReg = jQuery( 'button#icwp_u2f_key_reg' );
		let $oU2fStatus = jQuery( '#icwp_u2f_section p.description' );

		if ( !icwp_wpsf_vars_u2f.flags.is_validated ) {
			u2fApi.isSupported()
				  .then( function ( supported ) {
					  if ( supported ) {
						  $oBtnReg.prop( 'disabled', false );
						  $oBtnReg.on( 'click', function () {
							  u2fApi.register( icwp_wpsf_vars_u2f.registration )
									.then( function ( response ) {
										jQuery( '#icwp_wpsf_new_u2f_response' ).val( JSON.stringify( response ) )
										$oU2fStatus.text( icwp_wpsf_vars_u2f.strings.do_save );
										$oU2fStatus.css( 'font-weight', 'bolder' )
												   .css( 'color', 'green' );
									} )
									.catch( function ( response ) {
										console.log( response );
										$oU2fStatus.text( icwp_wpsf_vars_u2f.strings.failed );
										$oU2fStatus.css( 'font-weight', 'bolder' )
												   .css( 'color', 'red' );
									} );
						  } );
					  }
					  else {
						  $oBtnReg.prop( 'disabled', true );
						  $oU2fStatus.text( icwp_wpsf_vars_u2f.strings.not_supported );
					  }
				  } )
				  .catch();
		}

	} );
}