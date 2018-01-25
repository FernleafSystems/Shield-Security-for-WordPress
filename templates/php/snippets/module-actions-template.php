<div class="row no-gutters">
	<div class="col">
		<div class="btn-group float-right icwp-top-buttons" role="group" aria-label="Basic example">
			<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 0 );}"
			   class="btn btn-outline-success"><?php echo $strings['btn_options'];?></a>
			<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 1 );}"
			   class="btn btn-outline-info"><?php echo $strings['btn_help'];?></a>
			<a href="javascript:{ jQuery( '.icwp-carousel' ).carousel( 2 );}" aria-disabled="true"
			   class="btn btn-secondary disabled"><?php echo $strings[ 'btn_actions' ]; ?></a>
		</div>
	</div>
</div>

<?php if ( $flags[ 'show_content_actions' ] ) : ?>
	<div class="row no-gutters">
		<div class="col">
		<?php include_once( dirname( __FILE__ ).sprintf( '/module-actions-%s.php', $data[ 'mod_slug_short' ] ) ); ?>
		</div>
	</div>
<?php else: ?>
	<h3 style="margin: 10px 0 100px">No Actions For This Module</h3>
<?php endif; ?>