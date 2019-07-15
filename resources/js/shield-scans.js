/**
 */
jQuery.fn.icwpWpsfScansStart = function ( aOptions ) {

	let startScans = function ( evt ) {
		evt.preventDefault();
		sendReq( { 'form_params': $oThis.serialize() } );
		return false;
	};

	let sendReq = function ( aParams ) {
		iCWP_WPSF_BodyOverlay.show();

		let aReqData = aOpts[ 'ajax_scans_start' ];
		jQuery.post( ajaxurl, jQuery.extend( aReqData, aParams ),
			function ( oResponse ) {

				if ( oResponse.success ) {
					iCWP_WPSF_Toaster.showMessage( oResponse.data.message, oResponse.success );
					if ( oResponse.data.page_reload ) {
						location.reload();
					}
					else if ( oResponse.data.scans_running ) {
						jQuery( document ).icwpWpsfScansCheck(
							{
								'ajax_scans_check': aOpts[ 'ajax_scans_check' ]
							}
						);
					}
					else {
						plugin.options[ 'table' ].reloadTable();
						iCWP_WPSF_Toaster.showMessage( oResponse.data.message, oResponse.success );
					}
				}
				else {
					let sMessage = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						sMessage = oResponse.data.message;
					}
					alert( sMessage );
					iCWP_WPSF_BodyOverlay.hide();
				}

			}
		).fail( function () {
				alert( 'Scan failed because the site killed the request. ' +
					'Likely your webhost imposes a maximum time limit for processes, and this limit was reached.' );
				iCWP_WPSF_BodyOverlay.hide();
			}
		).always( function () {
			}
		);
	};

	let initialise = function () {
		jQuery( document ).ready( function () {
			$oThis.on( 'submit', startScans );
		} );
	};

	let $oThis = this;
	let aOpts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};

/**
 */
jQuery.fn.icwpWpsfScansCheck = function ( aOptions ) {

	let bFoundRunning = false;
	let bCurrentlyRunning = false;

	let sendReq = function ( aParams ) {
		iCWP_WPSF_BodyOverlay.show();

		let aReqData = aOpts[ 'ajax_scans_check' ];
		jQuery.post( ajaxurl, jQuery.extend( aReqData, aParams ),
			function ( oResponse ) {

				if ( oResponse.data.running !== undefined ) {
					for ( const scankey of Object.keys( oResponse.data.running ) ) {
						if ( oResponse.data.running[ scankey ] ) {
							bFoundRunning = true;
							bCurrentlyRunning = true;
							break;
						}
						bCurrentlyRunning = false;
					}
				}
			}
		).always( function () {
				if ( bCurrentlyRunning ) {
					setTimeout( function () {
						sendReq();
					}, 4000 );
				}
				else if ( bFoundRunning ) {
					location.reload();
				}
				else {
					iCWP_WPSF_BodyOverlay.hide();
				}
			}
		);
	};

	let initialise = function () {
		jQuery( document ).ready( function () {
			sendReq();
		} );
	};

	let $oThis = this;
	let aOpts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};