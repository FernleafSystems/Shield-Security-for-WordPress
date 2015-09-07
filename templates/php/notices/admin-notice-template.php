<?php
$sBaseDirName = dirname(__FILE__).ICWP_DS;
?>
<div id="<?php echo $unique_render_id;?>" class="<?php echo $notice_classes; ?> icwp-admin-notice notice is-dismissible">
<?php include_once( $sBaseDirName.$icwp_admin_notice_template.'.php' ); ?>
</div>


<script type="text/javascript">
	jQuery(document).on(
		'click',
		'#<?php echo $unique_render_id; ?> button.notice-dismiss',
		function() {
			var requestData = {
				'action': 'icwp_DismissAdminNotice',
				'_ajax_nonce': '<?php echo $icwp_ajax_nonce; ?>',
				'hide': '1',
				'notice_id': '<?php echo $render_slug; ?>'
			};
			jQuery.get( ajaxurl, requestData );
		}
	);
</script>