<h3><?php echo $strings['help_us']; ?></h3>
<p>
	<?php echo $strings['want_to_track']; ?> <?php echo $strings['what_we_collect']; ?>
	<br /><?php echo $strings['data_anon']; ?>
	<a target="_blank" href="<?php echo $hrefs['link_to_see']; ?>"><?php echo $strings['click_to_see']; ?></a>
</p>
<p>
	<a href="#" class="button button-primary" id="icwpButtonPluginTrackingAgree">Yes, I want to help by sharing this information!</a>
	<a href="#" class="button" id="icwpButtonPluginTrackingMore">Hmm, I'd like to learn more, please.</a>
	<a href="#" id="icwpButtonPluginTrackingDisagree">No, I don't want to help!</a>
</p>

<script type="text/javascript">
	var $oContainer = jQuery( '#<?php echo $unique_render_id; ?>' );

	jQuery( document ).on( 'click', 'a#icwpButtonPluginTrackingAgree', icwp_PluginTrackingAgree );
	jQuery( document ).on( 'click', 'a#icwpButtonPluginTrackingDisagree', icwp_PluginTrackingDisagree );

	function icwp_PluginTrackingAgree() {
		icwp_PluginTrackingAgreement(1);
	}

	function icwp_PluginTrackingDisagree() {
		icwp_PluginTrackingAgreement(0);
	}

	function icwp_PluginTrackingAgreement( $bAgree ) {
		var requestData = {
			'action': 'icwp_PluginTrackingPermission',
			'agree' : $bAgree,
			'_ajax_nonce': '<?php echo $icwp_ajax_nonce; ?>',
			'hide': '1',
			'notice_id': '<?php echo $notice_attributes['notice_id']; ?>'
		};
		jQuery.get( ajaxurl, requestData );
		$oContainer.fadeOut( 500, function() { $oContainer.remove(); } );
	}
</script>