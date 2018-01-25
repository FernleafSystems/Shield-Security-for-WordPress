<?php
?>

<div class="row no-gutters">
	<div class="col">
		<div class="btn-group float-right icwp-top-buttons" role="group" aria-label="Basic example">
			<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 0 );}"
			   class="btn btn-success">&larr; Go To Options</a>
			<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 2 );}"
			   class="btn btn-secondary">Go To Actions &rarr;</a>
		</div>
	</div>
</div>
<div class="row no-gutters">
	<div class="col">
		<?php include_once( dirname( __FILE__ ).sprintf( '/module-help-%s.php', $slug ) ); ?>
	</div>
</div>