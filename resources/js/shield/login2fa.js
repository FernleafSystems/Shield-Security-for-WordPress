jQuery( document ).ready( function () {

	let $body = jQuery( 'body' );
	let $theForm = jQuery( 'form#loginform' );
	let userID = jQuery( 'input[type=hidden]#wp_user_id' ).val();
	let loginNonce = jQuery( 'input[type=hidden]#login_nonce' ).val();
	let $u2fStart = jQuery( 'input#btn_u2f_start' );

	jQuery( 'input[type=text]:first', $theForm ).focus();

	jQuery( 'input#icwp_wpsf_sms_otp' ).on( 'click', function () {

		if ( confirm( 'Are you sure?' ) ) {
			let $this = jQuery( this );
			$this.attr( 'disabled', 'disabled' );
			let reqParamsStart = $this.data( 'ajax_intent_sms_send' );
			let ajaxurl = reqParamsStart.ajaxurl;
			delete reqParamsStart.ajaxurl;

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

	let ajax_intent_email_send = function () {
		let $this = jQuery( this );
		$this.attr( 'disabled', true );

		let reqParams = $emailInput.data( 'ajax_intent_email_send' );
		reqParams.wp_user_id = userID;
		reqParams.login_nonce = loginNonce;
		$body.addClass( 'shield-busy' );
		jQuery.post( reqParams.ajaxurl, reqParams, function ( response ) {
				let msg = 'Communications error with site.';

				if ( response.data.success ) {
					alert( response.data.message );
				}
				else {
					if ( response.data.message !== undefined ) {
						msg = response.data.message;
					}
					else {
						msg = 'Sending Email 2FA failed';
					}
					alert( msg );
				}
			}
		).always( function () {
				$body.removeClass( 'shield-busy' );
				$this.attr( 'disabled', false );
			}
		);
	};

	let $emailInput = jQuery( 'input[type=text]#icwp_wpsf_email_otp' );
	if ( $emailInput.length > 0 ) {
		$emailInput.val( '' );
		if ( Number( $emailInput.data( 'auto_send' ) ) === 1 ) {
			ajax_intent_email_send();
		}
		$emailInput.on( 'keyup change keydown', function () {
			this.value = this.value.toUpperCase();
			this.value = this.value.replace( /[^0-9A-Z]/, '' ).substring( 0, 6 );
		} );
		jQuery( 'a#ajax_intent_email_send' ).on( 'click', ajax_intent_email_send );
	}

	let $gaInput = jQuery( 'input[type=text]#icwp_wpsf_ga_otp' );
	if ( $gaInput.length > 0 ) {
		$gaInput.val( '' );
		$gaInput.on( 'keyup change keydown', function () {
			this.value = this.value.replace( /[^0-9]/, '' ).substring( 0, 6 );
		} );
	}

	if ( $u2fStart.length === 1 ) {
		u2fApi.isSupported()
			  .then( function ( supported ) {

				  if ( supported ) {

					  $u2fStart.on( 'click', function () {

						  u2fApi.sign( JSON.parse( atob( $u2fStart.data( 'signs' ) ) ) )
								.then( function ( response ) {
									jQuery( '<input>' ).attr( {
										type: 'hidden',
										name: 'u2f_signs',
										value: $u2fStart.data( 'signs' )
									} ).appendTo( $theForm );
									jQuery( '<input>' ).attr( {
										type: 'hidden',
										name: $u2fStart.data( 'input_otp' ),
										value: JSON.stringify( response )
									} ).appendTo( $theForm );
									/** Automatically submit the form for U2F **/
									$theForm[ 0 ].requestSubmit();
								} )
								.catch( function ( response ) {
									alert( 'U2F authentication failed. Reload the page to retry.' );
								} );
					  } );

				  }
				  else {
					  $u2fStart.val( 'U2F Not Supported' );
					  alert( "U2F Authentication isn't supported on this web browser." );
				  }
			  } )
			  .catch();
	}

} );