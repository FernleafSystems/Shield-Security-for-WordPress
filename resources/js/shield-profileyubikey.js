if ( typeof icwp_wpsf_vars_profileyubikey !== 'undefined' ) {
	jQuery.fn.icwpWpsfProfileYubikey = function () {

		var initialise = function () {
			jQuery( document ).ready( function () {
				jQuery( 'a.icwpWpsf-YubikeyRemove' ).on( 'click', function ( evt ) {
					evt.preventDefault();
					icwp_wpsf_vars_profileyubikey.ajax_remove.yubikeyid =
						jQuery( evt.target ).data( 'yubikeyid' );
					iCWP_WPSF_StandardAjax.send_ajax_req( icwp_wpsf_vars_profileyubikey.ajax_remove );
					return false;
				} )
			} );
		};

		initialise();
		return this;
	};

	jQuery( document ).icwpWpsfProfileYubikey();
}