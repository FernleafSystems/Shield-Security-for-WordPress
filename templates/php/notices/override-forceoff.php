<p><?php echo $strings[ 'message' ]; ?>. (<a href="#" id="ForceOffDelete"><?php echo $strings[ 'delete' ]; ?></a>)</p>

<script type="text/javascript">
	jQuery( document ).on(
		'click',
		'a#ForceOffDelete',
		delete_force_off
	);

	function delete_force_off() {
		iCWP_WPSF_BodyOverlay.show();
		jQuery.get( ajaxurl, <?php echo $ajax[ 'delete_forceoff' ]; ?> )
			  .always(
				  function () {
					  location.reload( true );
				  }
			  );

	}
</script>