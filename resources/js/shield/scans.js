jQuery.fn.icwpWpsfScansStart = function ( aOptions ) {

	let startScans = function ( evt ) {
		evt.preventDefault();
		sendReq( { 'form_params': $thisForm.serialize() } );
		return false;
	};

	let loadResultsPage = function ( evt ) {
		window.location.href = opts[ 'href_scans_results' ];
	};

	let sendReq = function ( param ) {
		iCWP_WPSF_BodyOverlay.show();

		jQuery.post( ajaxurl, jQuery.extend( opts[ 'ajax_scans_start' ], param ),
			function ( response ) {

				if ( response.success ) {
					iCWP_WPSF_Toaster.showMessage( response.data.message, response.success );
					if ( response.data.page_reload ) {
						loadResultsPage();
					}
					else if ( response.data.scans_running ) {
						setTimeout( function () {
							jQuery( document ).icwpWpsfScansCheck( opts );
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
			$thisForm.on( 'submit', startScans );
		} );
	};

	let $thisForm = this;
	let opts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};

jQuery.fn.icwpWpsfScansCheck = function ( aOptions ) {

	let bFoundRunning = false;
	let currentlyRunning = false;
	let nRunningCount = 0;

	let loadResultsPage = function ( evt ) {
		window.location.href = aOpts[ 'href_scans_results' ];
	};

	let sendReq = function ( aParams ) {
		iCWP_WPSF_BodyOverlay.show();

		let reqData = aOpts[ 'ajax_scans_check' ];
		jQuery.post( ajaxurl, jQuery.extend( reqData, aParams ),
			function ( response ) {

				currentlyRunning = false;
				nRunningCount = 0;
				if ( response.data.running !== undefined ) {
					for ( const scanKey of Object.keys( response.data.running ) ) {
						if ( response.data.running[ scanKey ] ) {
							nRunningCount++;
							bFoundRunning = true;
							currentlyRunning = true;
						}
					}
				}
				let modal = jQuery( '#ScanProgressModal' );
				jQuery( '.modal-body', modal ).html( response.data.vars.progress_html );
				modal.modal( 'show' );
			}
		).always( function () {
				if ( currentlyRunning ) {
					setTimeout( function () {
						sendReq();
					}, 3000 );
				}
				else {
					setTimeout( function () {
						loadResultsPage();
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

	let aOpts = jQuery.extend( {}, aOptions );
	initialise();

	return this;
};