if ( typeof icwp_wpsf_vars_lpantibot !== 'undefined' ) {
	var iCWP_WPSF_LoginGuard_Gasp = new function () {

		this.initialise = function () {
			jQuery( document ).ready( function () {
				jQuery( icwp_wpsf_vars_lpantibot.form_selectors ).each(
					function ( _ ) {
						if ( this !== null ) {
							if ( icwp_wpsf_vars_lpantibot.flags.recap ) {
								insertPlaceHolder_Recap( this );
							}
							if ( icwp_wpsf_vars_lpantibot.flags.gasp ) {
								insertPlaceHolder_Gasp( this );
							}
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

		/**
		 */
		var insertPlaceHolder_Gasp = function ( form ) {
			var uniq = icwp_wpsf_vars_lpantibot.uniq;
			var shiep = document.createElement( "p" );
			shiep.id = 'icwp_wpsf_login_p' + uniq;
			shiep.classList.add( 'icwpImHuman_' + uniq );
			shiep.innerHTML = '';

			var shishoney = document.createElement( "input" );
			shishoney.type = "hidden";
			shishoney.name = "icwp_wpsf_login_email";

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

			jQuery( shiep ).insertBefore( jQuery( ':submit', form ) );

			form.onsubmit = function () {
				if ( shieThe_cb.checked !== true ) {
					alert( icwp_wpsf_vars_lpantibot.strings.alert );
					return false;
				}
				return true;
			};
		};
	}();
	iCWP_WPSF_LoginGuard_Gasp.initialise();
}