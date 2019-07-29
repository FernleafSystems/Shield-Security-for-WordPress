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
						setTimeout( function () {
							jQuery( document ).icwpWpsfScansCheck(
								{
									'ajax_scans_check': aOpts[ 'ajax_scans_check' ]
								}
							);
						}, 4000 );
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
	let nRunningCount = 0;

	let sendReq = function ( aParams ) {
		iCWP_WPSF_BodyOverlay.show();

		let aReqData = aOpts[ 'ajax_scans_check' ];
		jQuery.post( ajaxurl, jQuery.extend( aReqData, aParams ),
			function ( oResp ) {

				bCurrentlyRunning = false;
				nRunningCount = 0;
				if ( oResp.data.running !== undefined ) {
					for ( const scankey of Object.keys( oResp.data.running ) ) {
						if ( oResp.data.running[ scankey ] ) {
							nRunningCount++;
							bFoundRunning = true;
							bCurrentlyRunning = true;
						}
					}
				}

				if ( oResp.data.vars.has_current ) {
					let $oModal = jQuery( '#ScanProgressModal' );
					jQuery( '.modal-body', $oModal ).html( oResp.data.vars.progress_html );
					$oModal.modal( 'show' );
					iCWP_WPSF_Toaster.showMessage( oResp.data.vars.current, true );
				}

			}
		).always( function () {
				if ( bCurrentlyRunning ) {
					setTimeout( function () {
						sendReq();
					}, 5000 );
				}
				else {
					iCWP_WPSF_Toaster.showMessage( 'Scans Complete.', true );
					location.reload();
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