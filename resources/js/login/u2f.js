jQuery( document ).ready( function () {

	jQuery( 'input#icwp_wpsf_sms_otp' ).on( 'click', function () {

		if ( confirm( 'Are you sure?' ) ) {
			let $this = jQuery( this );
			$this.attr( 'disabled', 'disabled' );
			let reqParamsStart = $this.data( 'ajax_intent_start' );
			let ajaxurl = reqParamsStart.ajaxurl;
			delete reqParamsStart.ajaxurl;

			let $body = jQuery( 'body' );
			$body.addClass( 'shield-busy' );
			jQuery.post( ajaxurl, reqParamsStart, function ( response ) {
					let msg = 'Communications error with site.';

					if ( response.data.success ) {
						alert( response.data.message );
						let newText = document.createElement( "input" );
						newText.classList.add( 'form-control' );
						let $newText = jQuery( newText );
						$newText.attr( 'autocomplete', 'off' );
						$newText.attr( 'placeholder', 'Enter SMS One-Time Password' );
						$newText.attr( 'name', $this.attr( 'name' ) );
						$newText.attr( 'id', $this.attr( 'id' ) );
						$newText.insertBefore( $this );
						$this.remove();
					}
					else {
						if ( response.data.message !== undefined ) {
							msg = response.data.message;
						}
						else {
							msg = 'Sending verification SMS failed';
						}
						alert( msg );
					}
				}
			).always( function () {
					reqParamsStart.ajaxurl = ajaxurl;
					$body.removeClass( 'shield-busy' );
					$this.removeAttr( 'disabled' );
				}
			);
		}
	} );

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