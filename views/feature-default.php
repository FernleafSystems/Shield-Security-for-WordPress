<div class="row">
	<div class="<?php echo $fShowAds? 'span10' : 'span12'; ?>">

		<div><?php include_once( $sBaseDirName.'options_form.php' ); ?></div>

		<?php if ( $fShowAds ) : ?>
			<div class="row-fluid">
				<div class="span12">
					<?php echo getWidgetIframeHtml( 'dashboard-widget-worpit-wtb' ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div><!-- / span9 -->

	<?php if ( $fShowAds ) : ?>
		<div class="span3" id="side_widgets">
			<?php echo getWidgetIframeHtml('side-widgets-wtb'); ?>
		</div>
	<?php endif; ?>
</div><!-- / row -->