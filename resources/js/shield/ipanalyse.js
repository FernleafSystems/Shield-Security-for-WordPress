jQuery.fn.icwpWpsfIpAnalyse = function ( options ) {

	let runAnalysis = function () {
		let $ipSelect = jQuery( ipReviewSelector );
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

		let reqData = opts[ 'ip_analyse_build' ];
		jQuery.post( ajaxurl, jQuery.extend( reqData, params ),
			function ( response ) {

				if ( response.success ) {
					jQuery( '#IpSelectContent' ).addClass( "d-none" );
					jQuery( '#IpReviewContent' ).removeClass( "d-none" )
												.html( response.data.html );
					if ( response.page_reload ) {
						location.reload();
					}
				}
				else {
					var msg = 'Communications error with site.';
					if ( response.data.message !== undefined ) {
						msg = response.data.message;
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

	let initialise = function () {

		jQuery( '#TabsIps a[data-toggle="tab"]' ).on( 'show.bs.tab', function ( e ) {
			clearAnalyseIpParam();
			localStorage.setItem( 'ipsActiveTab', jQuery( e.target ).attr( 'href' ) );
		} );

		jQuery( document ).on( 'click', 'a.ip_analyse_action', function ( evt ) {
			evt.preventDefault();
			if ( confirm( 'Are you sure?' ) ) {
				let $this = jQuery( this );
				let params = opts[ 'ip_analyse_action' ];
				params.ip = $this.data( 'ip' );
				params.ip_action = $this.data( 'ip_action' );
				iCWP_WPSF_StandardAjax.send_ajax_req( params );
			}
			return false;
		} );

		jQuery( document ).on( 'change', ipReviewSelector, runAnalysis );

		let urlParams = new URLSearchParams( window.location.search );
		let theIP = urlParams.get( 'analyse_ip' );
		if ( theIP ) {
			let $ipSelect = jQuery( ipReviewSelector );
			/**
			 * Since using dynamic AJAX requests to filter IP list,
			 * we must manually create an option and select it
			 */
			if ( $ipSelect.find( "option[value='" + theIP + "']" ).length === 0 ) {
				$ipSelect.append( new Option( theIP, theIP, true, true ) ).trigger( 'change' );
			}
			$ipSelect.val( theIP );
		}
		else {
			let activeTab = localStorage.getItem( 'ipsActiveTab' );
			if ( activeTab ) {
				jQuery( 'a[href="' + activeTab + '"]' ).tab( 'show' );
			}
		}
	};

	let opts = jQuery.extend( {}, options );
	let ipReviewSelector = '#IpReviewSelect';
	initialise();

	return this;
};