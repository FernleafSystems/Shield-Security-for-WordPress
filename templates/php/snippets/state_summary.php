<?php
if ( empty( $aSummaryData ) ) {
	return;
} ?>

<div class="feature-summary-blocks
	<?php echo $aSummary[ 'active' ] ? 'active-feature' : ''; ?>
	state-<?php echo $aSummary[ 'enabled' ] ? 'on' : 'off'; ?>"
>
	<?php foreach ( $aSummaryData as $nKey => $aSummary ) : ?>
		<div class="summary-state state-<?php echo $aSummary[ 'enabled' ] ? 'on' : 'off'; ?> <?php echo $aSummary[ 'active' ] ? 'active-feature' : ''; ?> "
			 id="feature-<?php echo $aSummary[ 'slug' ]; ?>">
			<a class="feature-icon"
				<?php echo sprintf( 'href="%s"', $aSummary[ 'href' ] ); ?>
			   title="<?php echo $aSummary[ 'name' ]; ?>"
			   data-content="<?php echo $aSummary[ 'enabled' ] ? $strings[ 'on' ] : $strings[ 'off' ]; ?>"
			   style="display: block; text-align: center; width: 100%;"></a>
		</div>
	<?php endforeach; ?>
	<div style="clear: both"></div>
</div>
<script type="text/javascript">
	jQuery( 'a.feature-icon' ).popover( {
		placement: 'left',
		trigger: 'hover'
	} );
	// jQuery( '#feature-plugin a.feature-icon' ).popover( 'show' );
</script>