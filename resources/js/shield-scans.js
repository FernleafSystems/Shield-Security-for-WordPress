/**
 * Important Params:
 * @param aOptions
 * @returns {jQuery}
 */
jQuery.fn.icwpWpsfScanResults = function ( aOptions ) {

	var deleteEntry = function ( evt ) {
		evt.preventDefault();
		iCWP_WPSF_BodyOverlay.show();
		var requestData = aOpts[ 'ajax_item_delete' ];
		requestData[ 'rid' ] = jQuery( this ).data( 'rid' );
		sendReq( requestData );
	};

	var ignoreEntry = function ( evt ) {
		evt.preventDefault();
		iCWP_WPSF_BodyOverlay.show();
		var requestData = aOpts[ 'ajax_item_ignore' ];
		requestData[ 'rid' ] = jQuery( this ).data( 'rid' );
		sendReq( requestData );
	};

	var repairEntry = function ( evt ) {
		alert( 'here1' );
		evt.preventDefault();
		var requestData = aOpts[ 'ajax_item_repair' ];
		requestData[ 'rid' ] = jQuery( this ).data( 'rid' );
		sendReq( requestData );
	};

	var sendReq = function ( requestData ) {
		iCWP_WPSF_BodyOverlay.show();

		jQuery.post( ajaxurl, jQuery.extend( requestData, aOpts[ 'req_params' ] ),
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
			console.log( aOpts[ 'table' ] );
			aOpts[ 'table' ].on( 'click', 'td.column-actions a.delete', deleteEntry );
			aOpts[ 'table' ].on( 'click', 'td.column-actions a.ignore', ignoreEntry );
			aOpts[ 'table' ].on( 'click', 'td.column-actions a.repair', repairEntry );
		} );
	};

	var $oThis = this;
	var aOpts = jQuery.extend( aOptions );
	initialise();

	return this;
};

/**
 * Important Params:
 * @param aOptions
 * @returns {jQuery}
 */
jQuery.fn.icwpWpsfScans = function ( aOptions ) {

	var startScan = function ( evt ) {
		evt.preventDefault();
		// init scan
		// init poll
		poll();
		return false;
	};

	var poll = function () {
		setTimeout( function () {

			jQuery.post( ajaxurl, {},
				function ( oResponse ) {
					if ( oResponse.data.success ) {
						// process poll results
						poll();
					}
					else {
					}

				}
			).always( function () {
				}
			);
		}, aOpts[ 'poll_interval' ] );
	};

	var initialise = function () {
		jQuery( document ).ready( function () {
			$oThis.on( 'submit', startScan );
		} );
	};

	var $oThis = this;
	var aOpts = jQuery.extend(
		{
			'poll_interval': 10000
		},
		aOptions
	);
	initialise();

	return this;
};