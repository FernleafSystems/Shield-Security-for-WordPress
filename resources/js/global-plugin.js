var iCWP_WPSF_SecurityAdmin = new function () {

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "submit", '#SecurityAdminForm', submit_admin_access );
		} );
	};

	var submit_admin_access = function ( event ) {
		iCWP_WPSF_BodyOverlay.show();
		event.preventDefault();

		var $oForm = jQuery( event.target );

		jQuery.post( ajaxurl, $oForm.serialize(), function ( oResponse ) {
			if ( oResponse.success ) {
				location.reload( true );
			}
			else {
				alert( 'Security Access Key was not recognised.' );
				iCWP_WPSF_BodyOverlay.hide();
			}
		} ).always( function () {
			}
		);

		return false;
	};
}();

var iCWP_WPSF_Autoupdates = new function () {

	var bRequestCurrentlyRunning = false;

	var togglePluginUpdate = function ( event ) {
		if ( bRequestCurrentlyRunning ) {
			return false;
		}

		$oInput = jQuery( this );

		if ( $oInput.data( 'disabled' ) !== 'no' ) {
			iCWP_WPSF_Growl.showMessage( $oInput.data( 'disabled' ), false );
			return false;
		}

		return sendTogglePluginAutoupdate( $oInput.data( 'pluginfile' ), $oInput.data( 'nonce' ) );
	};

	var sendTogglePluginAutoupdate = function ( sPluginFile, sAjaxNonce ) {
		bRequestCurrentlyRunning = true;

		var requestData = {
			'action': 'icwp_wpsf_TogglePluginAutoupdate',
			'pluginfile': sPluginFile,
			'_ajax_nonce': sAjaxNonce
		};

		jQuery.post( ajaxurl, requestData,
			function ( oResponse ) {
				iCWP_WPSF_Growl.showMessage( oResponse.data.message, oResponse.success );
			}
		).always( function () {
				bRequestCurrentlyRunning = false;
			}
		);

		return true;
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			jQuery( document ).on( "click", "input.icwp-autoupdate-plugin", togglePluginUpdate );
		} );
	};

}();

var iCWP_WPSF_Growl = new function () {

	this.showMessage = function ( sMessage, bSuccess ) {
		var $oDiv = createDynDiv( bSuccess ? 'success' : 'failed' );
		$oDiv.show().addClass( 'shown' );
		setTimeout( function () {
			$oDiv.html( sMessage );
		}, 300 );
		setTimeout( function () {
			$oDiv.css( 'width', 0 );
		}, 4000 );
		setTimeout( function () {
			$oDiv.html( '' )
				 .fadeOut();
		}, 4500 );
	};

	/**
	 */
	var createDynDiv = function ( sClass ) {
		var $oDiv = jQuery( '<div />' ).appendTo( 'body' );
		$oDiv.attr( 'id', 'icwp-growl-notice' + Math.floor( (Math.random() * 100) + 1 ) );
		$oDiv.addClass( sClass ).addClass( 'icwp-growl-notice' );
		return $oDiv;
	};

}();

var iCWP_WPSF_BodyOverlay = new function () {

	this.show = function () {
		jQuery( 'div#icwp-fade-wrapper' ).fadeIn( 1000 );
	};

	this.hide = function () {
		jQuery( 'div#icwp-fade-wrapper' ).stop().fadeOut();
	};

	this.initialise = function () {
		jQuery( document ).ready( function () {
			var $oDiv = jQuery( '<div />' )
			.attr( 'id', 'icwp-fade-wrapper' )
			.html( '<div class="icwp-waiting"></div>' )
			.appendTo( 'body' );
		} );
	};

}();

// iCWP_WPSF_Autoupdates.initialise();
iCWP_WPSF_BodyOverlay.initialise();
iCWP_WPSF_SecurityAdmin.initialise();