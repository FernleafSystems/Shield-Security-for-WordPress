jQuery.fn.icwpWpsfIpAnalyse = function ( options ) {

	var runAnalysis = function () {
		let newUrl = window.location.href.replace( /&analyse_ip=.+/i, "" );
		if ( $oIpSelect.val().length > 0 ) {
			newUrl += "&analyse_ip=" + $oIpSelect.val();
		}
		window.history.replaceState(
			{},
			document.title,
			newUrl
		);

		sendReq( { 'fIp': $oIpSelect.val() } );
	};

	var clearAnalyseIpParam = function () {
		window.history.replaceState(
			{},
			document.title,
			window.location.href.replace( /&analyse_ip=.*/i, "" )
		);
	};

	var sendReq = function ( params ) {
		iCWP_WPSF_BodyOverlay.show();

		jQuery( '#IpReviewContent' ).html( 'loading IP info ...' );

		var aReqData = aOpts[ 'ajax_build_ip_analyse' ];
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

		jQuery( '#TabsIps a[data-toggle="tab"]' ).on( 'show.bs.tab', function ( e ) {
			clearAnalyseIpParam();
			localStorage.setItem( 'ipsActiveTab', jQuery( e.target ).attr( 'href' ) );
		} );

		jQuery( document ).ready( function () {

			var $aIpActions = jQuery( document ).on( 'click', 'a.ip_analyse_action', function ( evt ) {
				evt.preventDefault();
				if ( confirm( 'Are you sure?' ) ) {
					let $oThis = jQuery( this );
					let params = aOpts[ 'ajax_ip_analyse_action' ];
					params.ip = $oThis.data( 'ip' );
					params.ip_action = $oThis.data( 'ip_action' );
					iCWP_WPSF_StandardAjax.send_ajax_req( params );
				}
				return false;
			} );

			$oIpSelect.on( 'change', runAnalysis );

			let urlParams = new URLSearchParams( window.location.search );
			let theIP = urlParams.get( 'analyse_ip' );
			if ( theIP ) {
				$oIpSelect.val( theIP );
				runAnalysis();
			}
			else {
				var activeTab = localStorage.getItem( 'ipsActiveTab' );
				if ( activeTab ) {
					jQuery( 'a[href="' + activeTab + '"]' ).tab( 'show' );
				}
			}

		} );
	};

	var $oIpSelect = jQuery( '#IpReviewSelect' );
	var aOpts = jQuery.extend( {}, options );
	initialise();

	return this;
};