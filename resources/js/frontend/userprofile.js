/** @var object shield_vars_userprofile */
jQuery.fn.ShieldUserProfile = function ( options ) {

	let $emailCheckbox = jQuery( 'input[type=checkbox]#shield_enable_mfaemail' );
	let $emailStartState;

	let initGA = function ( shield_vars ) {
		let $gaText = jQuery( 'input[type=text]#shield_gacode' );
		jQuery( document ).on( 'change, keyup', $gaText, function ( evt ) {
			$gaText.val( $gaText.val()
								.replace( /[^A-F0-9]/gi, '' )
								.toUpperCase()
								.substring( 0, 6 )
			);
			if ( $gaText.val().length === 6 ) {
				$gaText.prop( 'disabled', 'disabled' );
				shield_vars.ajax.user_ga_toggle.ga_otp = $gaText.val();
				sendReq( shield_vars.ajax.user_ga_toggle );
			}
		} );
		jQuery( document ).on( 'click', '#shield_ga_remove', function ( evt ) {
			sendReq( shield_vars.ajax.user_ga_toggle );
		} );
	}

	let initBackupcodes = function ( shield_vars ) {
		let $codeGen = jQuery(  );
		jQuery( document ).on( 'click', '#IcwpWpsfGenBackupLoginCode', function ( evt ) {
			sendReq( shield_vars.ajax.gen_backup_codes );
		} );
		jQuery( document ).on( 'click', '#IcwpWpsfDelBackupLoginCode', function ( evt ) {
			sendReq( shield_vars.ajax.del_backup_codes );
		} );
	};

	let initYubi = function ( shield_vars ) {
		let $yubiText = jQuery( 'input[type=text]#shield_yubi' );
		jQuery( document ).on( 'keyup', $yubiText, function ( evt ) {
			if ( evt.key === 'Enter' || evt.keyCode === 13 ) {
				shield_vars.ajax.user_yubikey_toggle.otp = $yubiText.val();
				sendReq( shield_vars.ajax.user_yubikey_toggle );
			}
		} );
	};

	let initEmail = function ( shield_vars ) {
		$emailStartState = $emailCheckbox.is( ':checked' );
		jQuery( document ).on( 'change', $emailCheckbox, function ( evt ) {
			if ( $emailStartState !== $emailCheckbox.is( ':checked' ) ) {
				shield_vars.ajax.user_email2fa_toggle.direction = $emailCheckbox.is( ':checked' ) ? 'on' : 'off';
				sendReq( shield_vars.ajax.user_email2fa_toggle );
			}
		} );
	}

	let initU2f = function ( shield_vars ) {

		let $registerButton = jQuery( 'button#icwp_u2f_key_reg' );
		let $oU2fStatus = jQuery( '#icwp_u2f_section p.description' );
		let oLabelRegEx = new RegExp( "^[a-zA-Z0-9_-]{1,16}$" );

		u2fApi.isSupported()
			  .then( function ( supported ) {
				  if ( supported ) {
					  $registerButton.prop( 'disabled', false );
					  $registerButton.on( 'click', function () {
						  let label = prompt( shield_vars.strings.prompt_dialog, "<Insert Label>" );
						  if ( typeof label === 'undefined' || label === null ) {
							  alert( shield_vars.strings.err_no_label )
						  }
						  else if ( !oLabelRegEx.test( label ) ) {
							  alert( shield_vars.strings.err_invalid_label )
						  }
						  else {
							  u2fApi.register( shield_vars.reg_request, shield_vars.signs )
									.then( function ( u2fResponse ) {
										u2fResponse.label = label;
										shield_vars.ajax.u2f_add.icwp_wpsf_new_u2f_response = u2fResponse;
										sendReq( shield_vars.ajax.u2f_add );
									} )
									.catch( function ( response ) {
										$oU2fStatus.text( shield_vars.strings.failed );
										$oU2fStatus.css( 'font-weight', 'bolder' )
												   .css( 'color', 'red' );
									} );
						  }
					  } );
				  }
				  else {
					  $registerButton.prop( 'disabled', true );
					  $oU2fStatus.text( shield_vars.strings.not_supported );
				  }
			  } )
			  .catch();

		jQuery( 'a.icwpWpsf-U2FRemove' ).on( 'click', function ( evt ) {
			evt.preventDefault();
			shield_vars.ajax.u2f_remove.u2fid = jQuery( evt.currentTarget ).data( 'u2fid' );
			sendReq( shield_vars.ajax.u2f_remove );
			return false;
		} );
	};

	var sendReq = function ( reqParams ) {
		let ajaxurl = reqParams.ajaxurl;
		delete reqParams.ajaxurl;

		jQuery( 'body' ).css( 'cursor', 'progress' );
		jQuery.post( ajaxurl, reqParams, function ( response ) {

				let msg = 'Communications error with site.';
				if ( response.data.message !== undefined ) {
					msg = response.data.message;
				}

				alert( msg );
				location.reload();
			}
		).always( function () {
			}
		);
	};

	var initialise = function () {
		jQuery( document ).ready( function () {
			if ( typeof shield_vars_userprofile.vars.providers.u2f !== typeof undefined ) {
				initU2f( shield_vars_userprofile.vars.providers.u2f );
			}
			if ( typeof shield_vars_userprofile.vars.providers.email !== typeof undefined ) {
				initEmail( shield_vars_userprofile.vars.providers.email );
			}
			if ( typeof shield_vars_userprofile.vars.providers.yubi !== typeof undefined ) {
				initYubi( shield_vars_userprofile.vars.providers.yubi );
			}
			if ( typeof shield_vars_userprofile.vars.providers.backupcode !== typeof undefined ) {
				initBackupcodes( shield_vars_userprofile.vars.providers.backupcode );
			}
		} );
	};

	initialise();

	return this;
};
jQuery( document ).ShieldUserProfile();