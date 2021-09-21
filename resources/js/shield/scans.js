jQuery.fn.icwpWpsfScansStart = function ( aOptions ) {

	let startScans = function ( evt ) {
		evt.preventDefault();
		sendReq( { 'form_params': $oThis.serialize() } );
		return false;
	};

	let loadResultsPage = function ( evt ) {
		window.location.href = aOpts[ 'href_scans_results' ];
	};

	let sendReq = function ( param ) {
		iCWP_WPSF_BodyOverlay.show();

		jQuery.post( ajaxurl, jQuery.extend( aOpts[ 'ajax_scans_start' ], param ),
			function ( response ) {

				if ( response.success ) {
					iCWP_WPSF_Toaster.showMessage( response.data.message, response.success );
					if ( response.data.page_reload ) {
						loadResultsPage();
					}
					else if ( response.data.scans_running ) {
						setTimeout( function () {
							jQuery( document ).icwpWpsfScansCheck( aOpts );
						}, 1000 );
					}
					else {
						plugin.options[ 'table' ].reloadTable();
						iCWP_WPSF_Toaster.showMessage( response.data.message, response.success );
					}
				}
				else {
					let msg = 'Communications error with site.';
					if ( response.data.message !== undefined ) {
						msg = response.data.message;
					}
					alert( msg );
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

jQuery.fn.icwpWpsfScansCheck = function ( aOptions ) {

	let bFoundRunning = false;
	let bCurrentlyRunning = false;
	let nRunningCount = 0;

	let sendReq = function ( aParams ) {
		iCWP_WPSF_BodyOverlay.show();

		let aReqData = aOpts[ 'ajax_scans_check' ];
		jQuery.post( ajaxurl, jQuery.extend( aReqData, aParams ),
			function ( response ) {

				bCurrentlyRunning = false;
				nRunningCount = 0;
				if ( response.data.running !== undefined ) {
					for ( const scankey of Object.keys( response.data.running ) ) {
						if ( response.data.running[ scankey ] ) {
							nRunningCount++;
							bFoundRunning = true;
							bCurrentlyRunning = true;
						}
					}
				}
				let modal = jQuery( '#ScanProgressModal' );
				jQuery( '.modal-body', modal ).html( response.data.vars.progress_html );
				modal.modal( 'show' );
			}
		).always( function () {
				if ( bCurrentlyRunning ) {
					setTimeout( function () {
						sendReq();
					}, 3000 );
				}
				else {
					setTimeout( function () {
						window.location.href = aOpts[ 'href_scans_results' ];
					}, 1000 );
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