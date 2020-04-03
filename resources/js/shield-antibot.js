if ( typeof icwp_wpsf_vars_lpantibot !== 'undefined' ) {
	var iCWP_WPSF_LoginGuard_Gasp = new function () {

		this.initialise = function () {
			jQuery( document ).ready( function () {
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
			} );
		};

		var insertPlaceHolder_Recap = function ( form ) {
			var recap_div = document.createElement( 'div' );
			recap_div.classList.add( 'icwpg-recaptcha' );
			jQuery( recap_div ).insertBefore( jQuery( ':submit', form ) );
		};

		var cleanDuplicates = function ( form ) {
			let $oPlaceholders = jQuery( 'p.shield_gasp_placeholder', form );
			if ( $oPlaceholders.length > 1 ) {
				$oPlaceholders.each(
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
			var shishoney = document.createElement( "input" );
			shishoney.type = "hidden";
			shishoney.name = "icwp_wpsf_login_email";

			shiep.innerHTML = '';
			shiep.appendChild( shishoney );

			var shieThe_lab = document.createElement( "label" );
			var shieThe_txt = document.createTextNode( ' ' + icwp_wpsf_vars_lpantibot.strings.label );
			var shieThe_cb = document.createElement( "input" );
			shieThe_cb.type = "checkbox";
			shieThe_cb.name = icwp_wpsf_vars_lpantibot.cbname;
			shieThe_cb.id = '_' + shieThe_cb.name;
			shiep.appendChild( shieThe_lab );
			shieThe_lab.appendChild( shieThe_cb );
			shieThe_lab.appendChild( shieThe_txt );

			let $oPH = jQuery( shiep );
			if ( [ 'p', 'P' ].includes( $oPH.parent()[ 0 ].nodeName ) ) {
				/** try to prevent nested paragraphs */
				jQuery( shiep ).insertBefore( $oPH.parent() )
			}

			let $oParentForm = $oPH.closest( 'form' );
			if ( $oParentForm.length > 0 ) {
				$oParentForm[ 0 ].onsubmit = function () {
					if ( shieThe_cb.checked !== true ) {
						alert( icwp_wpsf_vars_lpantibot.strings.alert );
						return false;
					}
					return true;
				};
			}
		};
	}();
	iCWP_WPSF_LoginGuard_Gasp.initialise();
}