jQuery.fn.icwpWpsfIpAnalyse = function ( options ) {

	var runAnalysis = function () {
		sendReq( { 'fIp': $oThis.val() } );
	};

	var sendReq = function ( params ) {
		iCWP_WPSF_BodyOverlay.show();

		var aReqData = aOpts[ 'build_ip_analyse' ];
		jQuery.post( ajaxurl, jQuery.extend( aReqData, params ),
			function ( oResponse ) {

				if ( oResponse.success ) {
					jQuery( '#IpReviewContent' ).html( oResponse.data.html );
				}
				else {
					var sMessage = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						sMessage = oResponse.data.message;
					}
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
		let myParam = urlParams.get( 'analyse_ip' );
		if ( myParam ) {
			$oThis.selectpicker( 'val', myParam );
			runAnalysis();
		}
	};

	var $oThis = this;
	var aOpts = jQuery.extend( {}, options );
	initialise();

	return this;
};