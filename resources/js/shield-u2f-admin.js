if ( typeof icwp_wpsf_vars_u2f !== 'undefined' ) {
	jQuery( document ).ready( function () {

		let $oBtnReg = jQuery( 'button#icwp_u2f_key_reg' );
		let $oU2fStatus = jQuery( '#icwp_u2f_section p.description' );
		let oLabelRegEx = new RegExp( "^[a-zA-Z0-9_-]{1,16}$" );

		if ( !icwp_wpsf_vars_u2f.flags.is_validated ) {
			u2fApi.isSupported()
				  .then( function ( supported ) {
					  if ( supported ) {
						  $oBtnReg.prop( 'disabled', false );
						  $oBtnReg.on( 'click', function () {
							  let label = prompt( "Please enter your name", "Harry Potter" );
							  if ( typeof label === 'undefined' || label === null ) {
								  alert( 'Please provide a label for this U2F device.' )
							  }
							  else if ( !oLabelRegEx.test( label ) ) {
								  alert( 'Device label must contain letters, numbers, underscore, or hypen, and be no more than 16 characters.' )
							  }
							  else {
								  u2fApi.register( icwp_wpsf_vars_u2f.registration )
										.then( function ( response ) {
											response.label = label;
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
							  }
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