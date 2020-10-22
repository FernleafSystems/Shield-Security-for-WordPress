jQuery( document ).ready( function () {

	u2fApi.isSupported()
		  .then( function ( supported ) {

			  let $oU2fStart = jQuery( 'input#btn_u2f_start' );

			  if ( supported ) {

				  $oU2fStart.on( 'click', function () {

					  u2fApi.sign( JSON.parse( atob( $oU2fStart.data( 'signs' ) ) ) )
							.then( function ( response ) {
								let $oForm = $oU2fStart.closest( 'form' );
								jQuery( '<input>' ).attr( {
									type: 'hidden',
									name: 'u2f_signs',
									value: $oU2fStart.data( 'signs' )
								} ).appendTo( $oForm );
								jQuery( '<input>' ).attr( {
									type: 'hidden',
									name: $oU2fStart.data( 'input_otp' ),
									value: JSON.stringify( response )
								} ).appendTo( $oForm );
								$oU2fStart.prop( 'disabled', true );
								$oU2fStart.val( 'U2F successful. Submit the form when ready.' );
							} )
							.catch( function ( response ) {
								alert( 'U2F authentication failed. Reload the page to retry.' );
							} );
				  } );

			  }
			  else {
				  $oU2fStart.prop( 'disabled', true );
				  $oU2fStart.val( "U2F Authentication isn't supported on this browser." );
				  alert( "U2F Authentication isn't supported on this browser." );
			  }
		  } )
		  .catch();

} );