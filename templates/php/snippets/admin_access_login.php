<div class="input-holder" id="AdminInputHolder-<?php echo $unique_render_id;?>">
	<label>
		<?php echo $admin_access_message; ?>:
		<input type="password" name="admin_access_key_request" data-nonce="<?php echo $sAjaxNonce; ?>" />
		<button type="submit">Go!</button>
	</label>
</div>

<script type="text/javascript">
	var $oThisAAL = jQuery('#AdminInputHolder-<?php echo $unique_render_id;?>');
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

		var requestData = {
			'action': '<?php echo $icwp_ajax_action; ?>',
			'icwp_ajax_action': '<?php echo $icwp_ajax_action; ?>',
			'icwp_nonce': '<?php echo $icwp_nonce; ?>',
			'icwp_nonce_action': '<?php echo $icwp_nonce_action; ?>',
			'icwp_action_module': '<?php echo $icwp_action_module; ?>',
			'admin_access_key_request': $oInput.val()
		};

		jQuery.post(ajaxurl, requestData, function( oResponse ) {
			if( oResponse.success ) {
				location.reload(true);
			}
			if( oResponse.data ) {
				$oThisAAL.html( oResponse.data.html );
			}
			else {
				$oThisAAL.html( 'There was an unknown error' );
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
		margin: 6% 107px;
		vertical-align: middle;
	}
	.input-holder input {
		font-size: inherit;
		height: 60px;
		vertical-align: middle;
		width: 180px;
	}
</style>