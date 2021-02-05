if ( typeof icwp_wpsf_vars_u2f !== 'undefined' ) {
	jQuery( document ).ready( function () {

		let $oBtnReg = jQuery( 'button#icwp_u2f_key_reg' );
		let $oU2fStatus = jQuery( '#icwp_u2f_section p.description' );
		let oLabelRegEx = new RegExp( "^[a-zA-Z0-9_-]{1,16}$" );

		u2fApi.isSupported()
			  .then( function ( supported ) {
				  if ( supported ) {
					  $oBtnReg.prop( 'disabled', false );
					  $oBtnReg.on( 'click', function () {
						  let label = prompt( icwp_wpsf_vars_u2f.strings.prompt_dialog, "<Insert Label>" );
						  if ( typeof label === 'undefined' || label === null ) {
							  alert( icwp_wpsf_vars_u2f.strings.err_no_label )
						  }
						  else if ( !oLabelRegEx.test( label ) ) {
							  alert( icwp_wpsf_vars_u2f.strings.err_invalid_label )
						  }
						  else {
							  u2fApi.register( icwp_wpsf_vars_u2f.reg_request, icwp_wpsf_vars_u2f.signs )
									.then( function ( response ) {
										response.label = label;
										jQuery( '#icwp_wpsf_new_u2f_response' ).val( JSON.stringify( response ) )
										$oU2fStatus.text( icwp_wpsf_vars_u2f.strings.do_save );
										$oU2fStatus.css( 'font-weight', 'bolder' )
												   .css( 'color', 'green' );
									} )
									.catch( function ( response ) {
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

	} );

	jQuery.fn.icwpWpsfProfileU2f = function () {

		var initialise = function () {
			jQuery( document ).ready( function () {
				jQuery( 'a.icwpWpsf-U2FRemove' ).on( 'click', function ( evt ) {
					evt.preventDefault();
					icwp_wpsf_vars_u2f.ajax.u2f_remove.u2fid = jQuery( evt.currentTarget ).data( 'u2fid' );
					iCWP_WPSF_StandardAjax.send_ajax_req( icwp_wpsf_vars_u2f.ajax.u2f_remove );
					return false;
				} )
			} );
		};

		initialise();
		return this;
	};

	jQuery( document ).icwpWpsfProfileU2f();
}