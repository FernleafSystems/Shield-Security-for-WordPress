<p><?php echo $strings[ 'want_to_track' ]; ?><?php echo $strings[ 'what_we_collect' ]; ?></p>
<p><?php echo $strings[ 'data_anon' ]; ?> <?php echo $strings[ 'can_turn_off' ]; ?>
	<a target="_blank" href="<?php echo $hrefs[ 'link_to_see' ]; ?>"><?php echo $strings[ 'click_to_see' ]; ?></a>
</p>
<p>
	<a href="#" class="button button-primary" id="icwpButtonPluginTrackingAgree">
		Yes, I'll share this info!</a>
	<a target="_blank" href="<?php echo $hrefs[ 'link_to_moreinfo' ]; ?>" class="button"
	   id="icwpButtonPluginTrackingMore">Hmm, I'd like to learn more, please.</a>
</p><p>
	<a href="#" id="icwpButtonPluginTrackingDisagree" style="float:right">No, I don't want to help!</a>
</p>

<script type="text/javascript">
	var $oContainer = jQuery( '#<?php echo $unique_render_id; ?>' );

	jQuery( document ).on( 'click', 'a#icwpButtonPluginTrackingAgree', icwp_PluginTrackingAgree );
	jQuery( document ).on( 'click', 'a#icwpButtonPluginTrackingDisagree', icwp_PluginTrackingDisagree );

	function icwp_PluginTrackingAgree() {
		icwp_PluginTrackingAgreement( 1 );
	}

	function icwp_PluginTrackingDisagree() {
		icwp_PluginTrackingAgreement( 0 );
	}

	function icwp_PluginTrackingAgreement( bAgree ) {
		var requestData = <?php echo $ajax[ 'set_plugin_tracking_perm' ]; ?>;
		requestData[ 'agree' ] = bAgree;
		requestData[ 'hide' ] = 1;
		requestData[ 'notice_id' ] = '<?php echo $notice_attributes[ 'notice_id' ]; ?>';

		jQuery.get( ajaxurl, requestData );
		$oContainer.fadeOut( 500, function () {
			$oContainer.remove();
		} );
	}
</script>