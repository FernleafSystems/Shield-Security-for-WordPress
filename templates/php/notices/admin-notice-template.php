<?php $sBaseDirName = __DIR__.DIRECTORY_SEPARATOR; ?>

<div id="<?php echo $unique_render_id; ?>"
	 class="<?php echo $notice_classes; ?> odp-admin-notice notice is-dismissible notice-<?php echo $icwp_admin_notice_template; ?>">

	<div class="notice-icon">
		<span class="dashicons dashicons-shield"></span>&nbsp;
	</div>

	<div class="notice-content">
		<h3 class="notice-title"><?php echo $strings[ 'title' ]; ?></h3>
		<?php require_once( $sBaseDirName.$icwp_admin_notice_template.'.php' ); ?>
	</div>

	<?php if ( !empty( $strings[ 'dismiss' ] ) ) : ?>
		<div class="dismiss-p">
			<a class="icwp-notice-dismiss" href="#"><?php echo $strings[ 'dismiss' ]; ?></a>
		</div>
	<?php endif; ?>

	<div style="clear:both;"></div>
</div>

<script type="text/javascript">
	jQuery( document ).on(
		'click',
		'#<?php echo $unique_render_id; ?> button.notice-dismiss, #<?php echo $unique_render_id; ?> a.icwp-notice-dismiss',
		icwp_dismiss_notice
	);

	function icwp_dismiss_notice() {
		var $oContainer = jQuery( '#<?php echo $unique_render_id; ?>' );
		var aData = <?php echo $ajax[ 'dismiss_admin_notice' ]; ?>;
		jQuery.get( ajaxurl, aData );
		$oContainer.fadeOut( 500, function () {
			$oContainer.remove();
		} );
	}
</script>