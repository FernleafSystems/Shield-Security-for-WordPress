<div id="IcwpCarouselOptions" class="icwp-carousel carousel slide carousel-fade">
	<div class="carousel-inner">

		<div class="carousel-item active">
			<div class="d-block w-100 content-options">
			<?php echo $flags[ 'show_standard_options' ] ? $content[ 'options_form' ] : ''; ?>
			<?php echo $flags[ 'show_alt_content' ] ? $content[ 'alt' ] : ''; ?>
			</div>
		</div>

		<div class="carousel-item carousel-wizards">
			<div class="d-block w-100"><?php echo $content[ 'wizard_landing' ]; ?></div>
		</div>

		<div class="carousel-item carousel-help">
			<div class="d-block w-100"><?php echo $content[ 'help' ]; ?></div>
		</div>
  </div>
</div>

<script>
	jQuery( document ).ready( function () {
			jQuery( '.icwp-carousel' ).carousel( {
				interval: false,
				keyboard: false
			} );
		}
	);
</script>