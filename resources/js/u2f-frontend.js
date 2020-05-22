jQuery( document ).ready( function () {

	var $oU2fInput = jQuery( '#icwp_wpsf_u2f_otp' );

	u2fApi.isSupported()
		  .then( function ( supported ) {
			  if ( supported ) {

				  var sign_request = {
					  'version': $oU2fInput.data( 'version' ),
					  'challenge': $oU2fInput.data( 'challenge' ),
					  'keyHandle': $oU2fInput.data( 'handle' ),
					  'appId': $oU2fInput.data( 'app_id' )
				  };
				  u2fApi.sign( sign_request )
						.then( function ( response ) {
							$oU2fInput.val( JSON.stringify( response ) );
							var $oForm = $oU2fInput.closest( 'form' );
							for ( let key in sign_request ) {
								jQuery( '<input>' ).attr( {
									type: 'hidden',
									name: key,
									value: sign_request[ key ]
								} ).appendTo( $oForm );
							}
							$oForm.submit();
						} )
						.catch( function ( response ) {
							alert( 'fail!' )
							console.log( response );
						} );
			  }
			  else {
			  }
		  } )
		  .catch();

} );