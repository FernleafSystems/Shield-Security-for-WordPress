jQuery.fn.icwpWpsfIpAnalyse = function ( options ) {

	var runAnalysis = function () {
		let newUrl = window.location.href.replace( /&analyse_ip=(\d{1,3}\.){3}\d{1,3}/i, "" );
		if ( $oThis.val().length > 0 ) {
			newUrl += "&analyse_ip=" + $oThis.val();
		}
		window.history.replaceState(
			{},
			document.title,
			newUrl
		);

		sendReq( { 'fIp': $oThis.val() } );
	};

	var sendReq = function ( params ) {
		iCWP_WPSF_BodyOverlay.show();

		jQuery( '#IpReviewContent' ).html( 'loading IP info ...' );

		var aReqData = aOpts[ 'build_ip_analyse' ];
		jQuery.post( ajaxurl, jQuery.extend( aReqData, params ),
			function ( oResponse ) {

				if ( oResponse.success ) {
					jQuery( '#IpSelectContent' ).addClass( "d-none" );
					jQuery( '#IpReviewContent' ).removeClass( "d-none" )
												.html( oResponse.data.html );
				}
				else {
					var sMessage = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						sMessage = oResponse.data.message;
					}
					jQuery( '#IpSelectContent' ).removeClass( "d-none" );
					jQuery( '#IpReviewContent' ).addClass( "d-none" );
					alert( sMessage );
				}

			}
		).always( function () {
				iCWP_WPSF_BodyOverlay.hide();
			}
		);
	};

	var initialise = function () {
		jQuery( document ).ready( function () {
			$oThis.on( 'change', runAnalysis );
		} );

		let urlParams = new URLSearchParams( window.location.search );
		let theIP = urlParams.get( 'analyse_ip' );
		if ( theIP ) {
			$oThis.selectpicker( 'val', theIP );
			runAnalysis();
		}
	};

	var $oThis = this;
	var aOpts = jQuery.extend( {}, options );
	initialise();

	return this;
};