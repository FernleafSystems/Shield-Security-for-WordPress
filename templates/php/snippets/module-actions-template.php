<div class="row no-gutters">
	<div class="col">
		<div class="module-headline">
			<h4>
				<?php echo $sPageTitle; ?>

				<div class="btn-group float-right icwp-top-buttons" role="group" aria-label="Basic example">
					<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 0 );}"
					   class="btn btn-outline-success"><?php echo $strings[ 'btn_options' ]; ?></a>

					<?php if ( $flags[ 'can_wizard' ] && $flags[ 'has_wizard' ] ) : ?>
						<a class="btn btn-outline-dark btn-icwp-wizard"
						   title="Launch Guided Walk-Through Wizards"
						   href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 1 );}">
								<?php echo $strings[ 'btn_wizards' ]; ?></a>
					<?php else : ?>
						<a class="btn btn-outline-dark btn-icwp-wizard"
						   href="javascript:{}"
							<?php if ( $flags[ 'can_wizard' ] ) : ?>
								title="No Wizards for this module."
							<?php else : ?>
								title="Wizards are not available as your PHP version is too old."
							<?php endif; ?>>
								<?php echo $strings[ 'btn_wizards' ]; ?></a>
					<?php endif; ?>

					<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 2 );}"
					   class="btn btn-outline-info"><?php echo $strings[ 'btn_help' ]; ?></a>

					<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 3 );}" aria-disabled="true"
					   class="btn btn-secondary disabled"><?php echo $strings[ 'btn_actions' ]; ?></a>
				</div>
				<small class="module-tagline"><?php echo $sTagline; ?></small>
			</h4>
		</div>
	</div>
</div>

<div class="row no-gutters content-help">
	<div class="col">
<?php if ( $flags[ 'show_content_actions' ] ) : ?>
	<?php include_once( dirname( __FILE__ ).sprintf( '/module-actions-%s.php', $data[ 'mod_slug_short' ] ) ); ?>
<?php else: ?>
	<h5 style="margin: 10px 0 100px">No Actions For This Module</h5>
<?php endif; ?>
	</div>
</div>