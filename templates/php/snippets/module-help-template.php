<div class="row no-gutters">
	<div class="col">
		<div class="btn-group float-right icwp-top-buttons" role="group" aria-label="Basic example">
			<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 0 );}"
			   class="btn btn-success"><?php echo $strings['btn_options'];?></a>
			<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 1 );}" aria-disabled="true"
			   class="btn btn-outline-info disabled"><?php echo $strings['btn_help'];?></a>
			<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 2 );}"
			   class="btn btn-secondary"><?php echo $strings['btn_actions'];?></a>
		</div>
	</div>
</div>
<div class="row no-gutters">
	<div class="col">
		<?php include_once( dirname( __FILE__ ).sprintf( '/module-help-%s.php', $data[ 'mod_slug_short' ] ) ); ?>
	</div>
</div>