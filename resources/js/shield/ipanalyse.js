jQuery.fn.icwpWpsfIpAnalyse = function ( options ) {

	var runAnalysis = function () {
		let newUrl = window.location.href.replace( /&analyse_ip=.+/i, "" );
		if ( $ipSelect.val() && $ipSelect.val().length > 0 ) {
			newUrl += "&analyse_ip=" + $ipSelect.val();
			sendReq( { 'fIp': $ipSelect.val() } );
		}
		window.history.replaceState(
			{},
			document.title,
			newUrl
		);

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

		var aReqData = aOpts[ 'ajax_ip_analyse_build' ];
		jQuery.post( ajaxurl, jQuery.extend( aReqData, params ),
			function ( oResponse ) {

				if ( oResponse.success ) {
					jQuery( '#IpSelectContent' ).addClass( "d-none" );
					jQuery( '#IpReviewContent' ).removeClass( "d-none" )
												.html( oResponse.data.html );
					if ( oResponse.page_reload ) {
						location.reload();
					}
				}
				else {
					var msg = 'Communications error with site.';
					if ( oResponse.data.message !== undefined ) {
						msg = oResponse.data.message;
					}
					jQuery( '#IpSelectContent' ).removeClass( "d-none" );
					jQuery( '#IpReviewContent' ).addClass( "d-none" );
					alert( msg );
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

			let $ipActions = jQuery( document ).on( 'click', 'a.ip_analyse_action', function ( evt ) {
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

			$ipSelect.on( 'change', runAnalysis );

			let urlParams = new URLSearchParams( window.location.search );
			let theIP = urlParams.get( 'analyse_ip' );
			if ( theIP ) {

				/**
				 * Since using dynamic AJAX requests to filter IP list,
				 * we must manually create an option and select it
				 */
				if ( $ipSelect.find( "option[value='" + theIP + "']" ).length === 0 ) {
					$ipSelect.append( new Option( theIP, theIP, true, true ) ).trigger( 'change' );
				}
				$ipSelect.val( theIP );
				runAnalysis();
			}
			else {
				let activeTab = localStorage.getItem( 'ipsActiveTab' );
				if ( activeTab ) {
					jQuery( 'a[href="' + activeTab + '"]' ).tab( 'show' );
				}
			}

		} );
	};

	var $ipSelect = jQuery( '#IpReviewSelect' );
	var aOpts = jQuery.extend( {}, options );
	initialise();

	return this;
};