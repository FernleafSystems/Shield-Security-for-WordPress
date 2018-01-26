<div class="row no-gutters">
	<div class="col">
		<div class="module-headline">
			<h4>
				<?php echo $sPageTitle; ?>
				<div class="btn-group float-right icwp-top-buttons" role="group" aria-label="Basic example">
					<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 0 );}"
					   class="btn btn-outline-success">&larr; <?php echo $strings[ 'btn_options' ]; ?></a>
					<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 1 );}" aria-disabled="true"
					   class="btn btn-info disabled"><?php echo $strings[ 'btn_help' ]; ?></a>
					<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 2 );}"
					   class="btn btn-outline-secondary"><?php echo $strings[ 'btn_actions' ]; ?> &rarr;</a>
				</div>
				<small class="module-tagline"><?php echo $sTagline; ?></small>
			</h4>
		</div>
	</div>
</div>

<div class="row no-gutters content-help">
	<div class="col">
	<?php include_once( dirname( __FILE__ ).sprintf( '/module-help-%s.php', $data[ 'mod_slug_short' ] ) ); ?>
	</div>
</div>