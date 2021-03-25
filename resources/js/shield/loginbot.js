var iCWP_WPSF_LoginGuard_Gasp = new function () {

	this.initialise = function () {
		jQuery( document ).ready( function () {
			if ( typeof icwp_wpsf_vars_lpantibot !== 'undefined' ) {

				jQuery( icwp_wpsf_vars_lpantibot.form_selectors ).each(
					function ( _ ) {
						if ( this !== null ) {
							if ( icwp_wpsf_vars_lpantibot.flags.captcha ) {
								insertPlaceHolder_Recap( this );
							}
							if ( icwp_wpsf_vars_lpantibot.flags.gasp ) {
								insertPlaceHolder_Gasp( this );
							}
						}
					}
				);

				jQuery( 'form' ).each(
					function ( _ ) {
						if ( this !== null ) {
							cleanDuplicates( this );
						}
					}
				);

				jQuery( 'p.shield_gasp_placeholder' ).each(
					function ( _ ) {
						if ( this !== null ) {
							processPlaceHolder_Gasp( this );
						}
					}
				);
			}
		} );
	};

	var insertPlaceHolder_Recap = function ( form ) {
		var recap_div = document.createElement( 'div' );
		recap_div.classList.add( 'icwpg-recaptcha' );
		jQuery( recap_div ).insertBefore( jQuery( ':submit', form ) );
	};

	var cleanDuplicates = function ( form ) {
		let $placeHolders = jQuery( 'p.shield_gasp_placeholder', form );
		if ( $placeHolders.length > 1 ) {
			$placeHolders.each(
				function ( nkey ) {
					if ( nkey > 0 && this !== null ) {
						jQuery( this ).remove();
					}
				}
			);
		}
	};

	var insertPlaceHolder_Gasp = function ( form ) {
		if ( jQuery( 'p.shield_gasp_placeholder', form ).length === 0 ) {
			let the_p = document.createElement( "p" );
			the_p.classList.add( 'shield_gasp_placeholder' );
			the_p.innerHTML = icwp_wpsf_vars_lpantibot.strings.loading + '&hellip;';
			jQuery( the_p ).insertBefore( jQuery( ':submit', form ) );
		}
	};

	var processPlaceHolder_Gasp = function ( shiep ) {
		var shieThe_lab = document.createElement( "label" );
		var shieThe_txt = document.createTextNode( ' ' + icwp_wpsf_vars_lpantibot.strings.label );
		var shieThe_cb = document.createElement( "input" );

		shiep.style.display = "inherit";

		let $oPH = jQuery( shiep );
		if ( [ 'p', 'P' ].includes( $oPH.parent()[ 0 ].nodeName ) ) {
			/** prevent nested paragraphs */
			jQuery( shiep ).insertBefore( $oPH.parent() )
		}

		let parentForm = $oPH.closest( 'form' );
		if ( parentForm.length > 0 ) {
			parentForm[ 0 ].addEventListener( "mouseover", function () {
				if ( !shieThe_cb.checked ) {
					// shieThe_cb.checked = true;
				}
			} );
			parentForm[ 0 ].onsubmit = function () {
				if ( !shieThe_cb.checked ) {
					alert( icwp_wpsf_vars_lpantibot.strings.alert );
					shiep.style.display = "inherit";
				}
				return shieThe_cb.checked;
			};

			var shishoney = document.createElement( "input" );
			shishoney.type = "hidden";
			shishoney.name = "icwp_wpsf_login_email";
			parentForm[ 0 ].appendChild( shishoney );
		}

		shiep.innerHTML = '';

		shieThe_cb.type = "checkbox";
		shieThe_cb.name = icwp_wpsf_vars_lpantibot.cbname;
		shieThe_cb.id = '_' + shieThe_cb.name;
		shiep.appendChild( shieThe_lab );
		shieThe_lab.appendChild( shieThe_cb );
		shieThe_lab.appendChild( shieThe_txt );
	};
}();
iCWP_WPSF_LoginGuard_Gasp.initialise();