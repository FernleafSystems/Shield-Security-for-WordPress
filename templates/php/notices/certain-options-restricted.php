<p>
	<?php echo $strings['notice_message']; ?>
</p>

<script type="text/javascript">
	jQuery( document ).ready(
		function() {
			aItems = [ <?php echo $js_snippets['options_to_restrict']; ?> ];
			aItems.forEach( disable_input );
		}
	);

	function disable_input( element, index, array ) {
		console.log(element);
		$oItem = jQuery( 'input[name=' + element + ']' );
		$oItem.prop( 'disabled', true );
		$oItem.parent().append(
			'<div class="restricted-option">' +
			'<span class="dashicons dashicons-lock"></span>' +
			'<?php echo $strings['editing_restricted'];?>'+' <?php echo $strings['unlock_link'];?>' +
			'</div>'
		);
	}
</script>
<style>
	.restricted-option {
		background-color: rgba(255, 255, 255, 0.6);
		border: 1px solid rgba(0, 0, 0, 0.2);
		clear: both;
		display: block;
		line-height: 22px;
		margin: 2px 0 4px;
		padding: 7px 8px 5px 6px;
	}
</style>