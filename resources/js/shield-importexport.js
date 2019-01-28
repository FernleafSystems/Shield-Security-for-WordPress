/**
 */
jQuery.fn.icwpWpsfImportExport = function ( aOptions ) {

	var importOptions = function ( evt ) {
		evt.preventDefault();
		sendReq( { 'form_params': $oThis.serialize() } );
		return false;
	};

	var sendReq = function ( aParams ) {
		iCWP_WPSF_BodyOverlay.show();

		jQuery.post( ajaxurl, jQuery.extend( aOpts[ 'ajax_options_import' ], aParams ),
			function ( oResponse ) {

				if ( oResponse.success ) {
					iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
					if ( oResponse.data.page_reload ) {
						location.reload( true );
					}
					else {
						iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
					}
				}
				else {
					var sMessage = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						sMessage = oResponse.data.message;
					}
					alert( sMessage );
					iCWP_WPSF_BodyOverlay.hide();
				}

			}
		).always( function () {
			}
		);
	};

	var initialise = function () {
		jQuery( document ).ready( function () {
			$oThis.on( 'submit', importOptions );
		} );
	};

	var $oThis = this;
	var aOpts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};