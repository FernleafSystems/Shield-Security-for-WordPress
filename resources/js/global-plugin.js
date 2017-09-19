function icwpTogglePluginUpdate( oInput ) {
	$oInput = jQuery( oInput );
	icwpSendTogglePluginAutoupdate( $oInput.data( 'pluginfile' ), $oInput.data( 'nonce' ) );
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
}

function icwpShowGrowl( sMessage, bSuccess ) {

	nRandom = Math.floor((Math.random() * 100) + 1);

	var $oDiv = jQuery('<div />').appendTo('body');
	$oDiv.attr('id', 'icwp-growl-notice'+nRandom);
	$oDiv.addClass( bSuccess ? 'success' : 'failed' )
		 .addClass( 'icwp-growl-notice' );

	$oDiv.fadeIn().html( sMessage );
	setTimeout( function () {
		$oDiv.fadeOut();
		$oDiv.remove();
	}, 3000 );
}