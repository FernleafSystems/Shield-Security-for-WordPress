<?php
if ( empty($aSummaryData) ) {
	return;
} ?>

<div class="row-fluid feature-summary-blocks">
	<div class="span1"></div>
	<?php foreach( $aSummaryData as $nKey => $aSummary ) : ?>
	<div class="span1 state-<?php echo $aSummary['enabled'] ? 'on' : 'off'; ?>" id="feature-<?php echo $aSummary['slug']; ?>" >
		<a class="" <?php echo sprintf( 'href="%s"', $aSummary['href'] ) ;?> title="<?php echo $aSummary['name']; ?> : <?php echo $aSummary['enabled'] ? $strings['on'] : $strings['off']; ?>" >
			<div class="feature-icon span3"></div>
		</a>
	</div>
	<?php endforeach; ?>
</div>