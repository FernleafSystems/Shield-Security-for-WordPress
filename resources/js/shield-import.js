/**
 */
jQuery.fn.icwpWpsfImportExport = function ( aOptions ) {

	var startScans = function ( evt ) {
		evt.preventDefault();
		sendReq( { 'form_params': $oThis.serialize() } );
		return false;
	};

	var sendReq = function ( aParams ) {
		iCWP_WPSF_BodyOverlay.show();

		var aReqData = aOpts[ 'ajax_import_from_site' ];
		jQuery.post( ajaxurl, jQuery.extend( aReqData, aParams ),
			function ( oResponse ) {

				if ( oResponse.success ) {
					iCWP_WPSF_Toaster.showMessage( oResponse.data.message, oResponse.success );
					location.reload( true );
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
			$oThis.on( 'submit', startScans );
		} );
	};

	var $oThis = this;
	var aOpts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};