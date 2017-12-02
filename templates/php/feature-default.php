<div class="row">
	<div class="<?php echo $flags[ 'show_ads' ] ? 'span10' : 'span10'; ?>">

		<?php echo $flags[ 'show_standard_options' ] ? $options_form : ''; ?>
		<?php echo $flags[ 'show_alt_content' ] ? $content[ 'alt' ] : ''; ?>

		<?php if ( $flags[ 'show_ads' ] ) : ?>
			<div class="row-fluid">
				<div class="span12">
					<?php echo getWidgetIframeHtml( 'dashboard-widget-worpit-wtb' ); ?>
				</div>
			</div>
		<?php endif; ?>
	</div><!-- / span9 -->

		<div class="span2" id="side_widgets">

		<?php if ( isset( $flags[ 'show_summary' ] ) && $flags[ 'show_summary' ] ) : ?>
			<?php include_once( $sBaseDirName.'snippets'.DIRECTORY_SEPARATOR.'state_summary.php' ); ?>
		<?php endif; ?>
		</div>

<!--	--><?php //if ( $flags[ 'show_ads' ] ) : ?>
<!--		<div class="span3" id="side_widgets">-->
<!--			--><?php //echo getWidgetIframeHtml( 'side-widgets-wtb' ); ?>
<!--		</div>-->
<!--	--><?php //endif; ?>
</div>