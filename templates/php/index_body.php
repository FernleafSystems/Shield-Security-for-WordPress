<div class="row no-gutters" id="ModulePageTopRow">

    <div class="col-1 modules smoothwidth" id="ColumnModules">
		<div id="TopPluginIcon" class="img-fluid">&nbsp;</div>
		<div class="nav flex-column">
		<?php foreach ( $aSummaryData as $nKey => $aSummary ) : ?>
			<a class="nav-link module <?php echo $aSummary[ 'active' ] ? 'active' : ''; ?>"
			   id="tab-<?php echo $aSummary[ 'slug' ]; ?>"
			   data-toggle="tooltip" data-placement="right" data-trigger="hover"
			   title="<?php echo $aSummary[ 'name' ] ?>"
			   href="<?php echo $aSummary[ 'href' ]; ?>" role="tab">
				<div class="module-icon" id="module-<?php echo $aSummary[ 'slug' ] ?>">
				</div>
			</a>
		<?php endforeach; ?>
		</div>
	</div>

    <div class="col" id="ColumnOptions">
		<?php include( $sBaseDirName.'feature-default.php' ); ?>
	</div>
</div>
<script>
	jQuery( 'a.nav-link.module' ).tooltip();
</script>