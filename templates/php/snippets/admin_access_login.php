<?php ?>

<div id="WpsfAdminAccessLogin" style="display:none;">
	<div class="bootstrap-wpadmin wpsf-admin-access-login" id="AdminAccessLogin-<?php echo $unique_render_id;?>">
		<div class="input-holder">
			<label>
				Enter your Admin Access Key:
				<input type="password" name="icwp-wpsf-admin_access_key_request" data-nonce="<?php echo $sAjaxNonce; ?>" />
				<button type="submit">Go!</button>
			</label>
		</div>
	</div>
</div>

<script type="text/javascript">
	var $oThisAAL = jQuery('#AdminAccessLogin-<?php echo $unique_render_id;?>');
	var $oInput = jQuery( 'input', $oThisAAL );
	var $oSubmit = jQuery( 'button', $oThisAAL );
	jQuery( document ).ready(
		function() {
			$oSubmit.click( submit_admin_access );
			$oInput.keypress( function(e) {
				if( e.which == 13 ) {
					submit_admin_access();
				}
			});
		}
	);

	function submit_admin_access() {
		$oThisAAL.html( '<div class="spinner"></div>');
		$oInput.prop( 'disabled', true );
		send_admin_access_login( $oInput.val(), $oInput.data('nonce') );
		$oInput.prop( 'disabled', false );
	}

	function send_admin_access_login( $sKey, $sNonce ) {

		var requestData = {
			'action': 'icwp_wpsf_AdminAccessLogin',
			'icwp_wpsf_admin_access_key_request': $sKey,
			'_ajax_nonce': $sNonce
		};

		jQuery.post(ajaxurl, requestData, function( oResponse ) {

			if( oResponse.success ) {
				location.reload( true );
			}
			else {
				alert( 'Failed to authenticate the key. Warning! Repeated attempts may lock you out of this site.' );
			}

		});
	}

</script>

<style type="text/css">
	.input-holder label {
	font-size: 24px;
	}
	.input-holder label {
	font-size: inherit;
		display: block;
		margin: 10% 107px;
		vertical-align: middle;
	}
	.input-holder input {
	font-size: inherit;
		height: 60px;
		vertical-align: middle;
		width: 180px;
	}
</style>