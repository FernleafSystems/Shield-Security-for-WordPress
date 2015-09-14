<p>
	<?php echo $strings['notice_message']; ?>
	- <strong><?php echo $strings['including_message']; ?></strong>
	<br/><?php echo $strings['your_ip']; ?>
</p>

<script type="text/javascript">
	jQuery( document ).ready(
		function() {
			aItems = [ 'blogname', 'blogdescription', 'siteurl', 'home', 'admin_email' ];
			aItems.forEach( disable_input );
		}
	);

	function disable_input( element, index, array ) {
		$oItem = jQuery( 'input[name=' + element + ']' );
		$oItem.prop( 'readonly', true );
		$oItem.parent().append('<div class="restricted-option"><span class="dashicons dashicons-lock"></span>Editing this option is current restricted.</div>');
	}
</script>
<style>
	.restricted-option {
		clear: both;
		display: block;
		line-height: 22px;
		padding: 3px 0;
	}
</style>