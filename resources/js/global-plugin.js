function icwpTogglePluginUpdate( event ) {
	$oInput = jQuery( this );

	if ( $oInput.data( 'disabled' ) !== 'no' ) {
		icwpShowGrowl( $oInput.data( 'disabled' ), false );
		return false;
	}

	$oInput.parent().addClass( 'icwp-waiting' );
	$bSuccess = icwpSendTogglePluginAutoupdate( $oInput.data( 'pluginfile' ), $oInput.data( 'nonce' ) );
	$oInput.parent().removeClass( 'icwp-waiting' );
	return $bSuccess;
}

jQuery( document ).ready( function () {
	jQuery( document ).on( "click", "input.icwp-autoupdate-plugin", icwpTogglePluginUpdate );
} );

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

function icwpSendTogglePluginAutoupdate( $sPluginFile, $sAjaxNonce ) {

	var requestData = {
		'action': 'icwp_wpsf_TogglePluginAutoupdate',
		'pluginfile': $sPluginFile,
		'_ajax_nonce': $sAjaxNonce
	};

	jQuery.post(ajaxurl, requestData, function( oResponse ) {
		icwpShowGrowl( oResponse.data.message, oResponse.success );
	});

	return true;
}

function icwpShowGrowl( sMessage, bSuccess ) {

	nRandom = Math.floor((Math.random() * 100) + 1);

	var $oDiv = jQuery('<div />').appendTo('body');
	$oDiv.attr('id', 'icwp-growl-notice'+nRandom);
	$oDiv.addClass( bSuccess ? 'success' : 'failed' )
		 .addClass( 'icwp-growl-notice' );

	$oDiv.fadeIn().html( sMessage );
	setTimeout( function () {
		$oDiv.fadeOut( 5000 );
		$oDiv.remove();
	}, 4000 );
}