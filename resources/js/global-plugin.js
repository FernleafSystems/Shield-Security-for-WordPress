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
		console.log( oResponse );
		if( oResponse.data ) {
			console.log( 'has data' );
		}
		else {
			console.log( 'no data' );
		}
	});
}