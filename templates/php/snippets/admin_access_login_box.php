<div id="WpsfAdminAccessLogin" style="display:none;">
	<div class="bootstrap-wpadmin wpsf-admin-access-login" id="AdminAccessLogin-<?php echo $unique_render_id; ?>"></div>
</div>

<script type="text/javascript">

	<?php if ( $flags[ 'restrict_options' ] ) : ?>
		jQuery( document ).ready(
			function () {
				aItems = [ <?php echo $js_snippets[ 'options_to_restrict' ]; ?> ];
				aItems.forEach( disable_input );
			}
		);
	<?php endif; ?>

	function disable_input( element, index, array ) {
		$oItem = jQuery( 'input[name=' + element + ']' );
		$oItem.prop( 'disabled', true );
		$oParentTr = $oItem.parents( 'tr' );
		$oParentTr.addClass( 'restricted-option-row' );
		$oItem.parents( 'td' ).append(
			'<div style="clear:both"></div><div class="restricted-option">' +
			'<span class="dashicons dashicons-lock"></span>' +
			'<?php echo $strings[ 'editing_restricted' ];?>' + ' <?php echo $strings[ 'unlock_link' ];?>' +
			'</div>'
		);
	}

	jQuery( document ).ready( function () {
		load_admin_access_form( jQuery( '#AdminAccessLogin-<?php echo $unique_render_id;?>' ) );
	} );

	function load_admin_access_form( $oTarget ) {
		var aData = <?php echo $ajax[ 'sec_admin_login_box' ]; ?>;
		request_and_html( aData, $oTarget );
	}

	function request_and_html( requestData, $oTarget ) {

		$oTarget.html( '<div class="spinner"></div>' );
		jQuery.post( ajaxurl, requestData, function ( oResponse ) {
			if ( oResponse.data ) {
				$oTarget.html( oResponse.data.html );
			}
			else {
				$oTarget.html( 'There was an unknown error' );
			}
		} );
	}
</script>