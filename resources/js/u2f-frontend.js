jQuery( document ).ready( function () {

	setTimeout(
		function () {
			let $oU2fInput = jQuery( '#icwp_wpsf_u2f_otp' );

			u2fApi.isSupported()
				  .then( function ( supported ) {
					  if ( supported ) {

						  let aSigns = JSON.parse( atob( $oU2fInput.data( 'signs' ) ) );
						  u2fApi.sign( aSigns )
								.then( function ( response ) {
									$oU2fInput.val( JSON.stringify( response ) );
									let $oForm = $oU2fInput.closest( 'form' );
									jQuery( '<input>' ).attr( {
										type: 'hidden',
										name: 'u2f_signs',
										value: $oU2fInput.data( 'signs' )
									} ).appendTo( $oForm );
									$oForm.submit();
								} )
								.catch( function ( response ) {
									alert( 'U2F authentication failed. Reload the page to retry.' )
								} );
					  }
					  else {
					  }
				  } )
				  .catch();
		},
		1000
	);

} );