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
		$oDiv.fadeIn().html( sMessage );
		setTimeout( function () {
			$oDiv.fadeOut( 5000 );
			$oDiv.remove();
		}, 4000 );
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

function showIcwpOverlay() {
	var $oDiv = jQuery( '<div />' ).prepend( 'body' );
	$oDiv.attr( 'id', 'icwp-fade-wrapper' );
	jQuery( '#icwp-fade-wrapper' ).show();
}

function hideIcwpOverlay() {
	var $oDiv = jQuery( '#icwp-fade-wrapper' );
	$oDiv.fadeOut();
	$oDiv.remove();
}

iCWP_WPSF_Autoupdates.initialise();