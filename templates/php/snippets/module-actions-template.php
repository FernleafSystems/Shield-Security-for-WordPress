<div class="row no-gutters">
	<div class="col">
		<div class="module-headline">
			<h4>
				<?php echo $sPageTitle; ?>

				<div class="btn-group float-right icwp-top-buttons" role="group">

					<a href="javascript:void(0)" class="btn btn-outline-success icwp-carousel-0">
						<?php echo $strings[ 'btn_options' ]; ?></a>

					<?php if ( $flags[ 'has_wizard' ] ) : ?>
						<a class="btn btn-outline-dark btn-icwp-wizard icwp-carousel-1"
						   title="Launch Guided Walk-Through Wizards" href="javascript:void(0)">
								<?php echo $strings[ 'btn_wizards' ]; ?></a>
					<?php else : ?>
						<a class="btn btn-outline-dark btn-icwp-wizard disabled"
						   href="javascript:{}"
						   title="No Wizards for this module."
						<?php echo $strings[ 'btn_wizards' ]; ?></a>
					<?php endif; ?>

					<a href="javascript:void(0)" class="btn btn-outline-info icwp-carousel-2">
						<?php echo $strings[ 'btn_help' ]; ?></a>
				</div>
				<small class="module-tagline"><?php echo $sTagline; ?></small>
			</h4>
		</div>
	</div>
</div>