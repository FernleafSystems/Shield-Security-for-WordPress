/**
 * Important Params:
 * 'table': icwpWpsfAjaxTable
 * @param aOptions
 * @returns {jQuery}
 */
jQuery.fn.icwpWpsfTableIps = function ( aOptions ) {

	var addIpFromFormSubmit = function ( event ) {
		event.preventDefault();
		iCWP_WPSF_BodyOverlay.show();

		jQuery.post( ajaxurl, jQuery( this ).serialize(),
			function ( oResponse ) {
				if ( oResponse.success ) {
					aOpts[ 'table' ].reloadTable();
					iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
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

	var deleteEntry = function ( evt ) {
		evt.preventDefault();
		iCWP_WPSF_BodyOverlay.show();

		var requestData = aOpts[ 'ajax_delete_ip' ];
		requestData[ 'rid' ] = jQuery( this ).data( 'rid' );

		jQuery.post( ajaxurl, requestData,
			function ( oResponse ) {
				if ( oResponse.success ) {
					aOpts[ 'table' ].reloadTable();
					iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
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
			$oThis.on( 'submit', 'form', addIpFromFormSubmit );
			aOpts[ 'table' ].on( 'click', 'td.column-actions a.delete', deleteEntry );
		} );
	};

	var $oThis = this;
	var aOpts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};