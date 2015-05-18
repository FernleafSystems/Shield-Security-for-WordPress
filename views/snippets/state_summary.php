<?php
if ( empty($aSummaryData) ) {
	return;
} ?>

<div class="row-fluid">
	<div class="span">
		<h3><?php _wpsf_e('Plugin Activated Features Summary:');?></h3>
	</div>
</div>
<div class="row-fluid feature-summary-blocks">
	<?php foreach( $aSummaryData as $nKey => $aSummary ) : ?>
	<?php if ( $nKey > 0 && ($nKey % 4 == 0) ) : ?>
</div>
<div class="row-fluid feature-summary-blocks">
	<?php endif; ?>
	<div class="span3 feature-summary-block state-<?php echo $aSummary['enabled'] ? 'on' : 'off'; ?>"
		 id="feature-<?php echo $aSummary['slug']; ?>"
		>
		<div class="row-fluid">
			<div class="feature-icon span3">
			</div>
			<div class="span8 offset1">
				<a class="btn btn-<?php echo $aSummary['enabled'] ? 'success':'warning';?>" <?php echo sprintf( 'href="%s"', $aSummary['href'] ) ;?> >
					<?php echo $strings['go_to_settings']; ?>
				</a>
			</div>
		</div>
		<div class="feature-name">
			<?php echo $aSummary['name']; ?> : <?php echo $aSummary['enabled'] ? $strings['on'] : $strings['off']; ?>
		</div>
	</div>
	<?php endforeach; ?>
</div>
