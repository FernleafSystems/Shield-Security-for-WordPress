<?php
if ( empty($aSummaryData) ) {
	return;
} ?>

<div class="row-fluid feature-summary-blocks">
	<?php foreach( $aSummaryData as $nKey => $aSummary ) : ?>
		<div class="span1 summary-state state-<?php echo $aSummary['enabled'] ? 'on' : 'off'; ?> <?php echo $aSummary['active'] ? 'active-feature' : ''; ?> " id="feature-<?php echo $aSummary['slug']; ?>" >
			<a class="feature-icon span3"
				<?php echo sprintf( 'href="%s"', $aSummary['href'] ) ;?>
			   title="<?php echo $aSummary['name']; ?> : <?php echo $aSummary['enabled'] ? $strings['on'] : $strings['off']; ?>"
			   style="display: block; text-align: center; width: 100%;"
			></a>
			<p><?php echo $aSummary['menu_title']; ?></p>
		</div>
	<?php endforeach; ?>
</div>