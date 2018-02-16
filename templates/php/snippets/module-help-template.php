<div class="row">
	<div class="col-6">
		<div class="module-headline">
			<h4><?php echo $sPageTitle; ?>
				<small class="module-tagline"><?php echo $sTagline; ?></small>
			</h4>
		</div>
	</div>
	<div class="col-6">
		<div class="module-headline">
			<div class="float-right">

				<div class="btn-group icwp-top-buttons" role="group">

					<a href="javascript:void(0)" class="btn btn-outline-success icwp-carousel-0">
						<?php echo $strings[ 'btn_options' ]; ?>
					</a>

					<?php if ( $flags[ 'can_wizard' ] && $flags[ 'has_wizard' ] ) : ?>
						<a class="btn btn-outline-dark btn-icwp-wizard icwp-carousel-1"
						   title="Launch Guided Walk-Through Wizards"
						   href="javascript:void(0)">
							<?php echo $strings[ 'btn_wizards' ]; ?></a>
					<?php else : ?>
						<a class="btn btn-outline-dark btn-icwp-wizard disabled"
						   href="javascript:{}"
							<?php if ( $flags[ 'can_wizard' ] ) : ?>
								title="No Wizards for this module."
							<?php else : ?>
								title="Wizards are not available as your PHP version is too old."
							<?php endif; ?>>
							<?php echo $strings[ 'btn_wizards' ]; ?></a>
					<?php endif; ?>

					<a href="javascript:void(0)" aria-disabled="true" class="btn btn-info disabled">
						<?php echo $strings[ 'btn_help' ]; ?>
					</a>

					<?php if ( $flags[ 'show_content_actions' ] ) : ?>
						<a class="btn btn-outline-secondary icwp-carousel-3" href="javascript:void(0)">
							<?php echo $strings[ 'btn_actions' ]; ?></a>
					<?php else : ?>
						<a class="btn btn-outline-secondary disabled" href="javascript:void(0)">
							<?php echo $strings[ 'btn_actions' ]; ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row no-gutters content-help">
	<div class="col">
	<?php
	$sFile = dirname( __FILE__ ).sprintf( '/module-help-%s.php', $data[ 'mod_slug_short' ] );
	if ( file_exists( $sFile  ) ) {
		include_once( $sFile );
	} ?>
	</div>
</div>