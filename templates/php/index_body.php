<div class="row" id="BackToInsights">
	<div class="col">
		<a class="btn btn-block btn-lg btn-info font-weight-bold"
		   href="<?php echo $hrefs[ 'back_to_dashboard' ]; ?>">
			&larr; <?php echo $strings[ 'back_to_dashboard' ]; ?>
		</a>
	</div>
</div>

<div class="row no-gutters" id="ModulePageTopRow">

    <div class="col-2 modules smoothwidth" id="ColumnModules">
		<div id="TopPluginIcon" class="img-fluid">&nbsp;</div>
		<div class="nav flex-column">
		<?php foreach ( $aSummaryData as $nKey => $aSummary ) : ?>
			<a class="nav-link module <?php echo $aSummary[ 'active' ] ? 'active' : ''; ?>"
			   id="tab-<?php echo $aSummary[ 'slug' ]; ?>"
			   href="<?php echo $aSummary[ 'href' ]; ?>" role="tab">
				<div class="module-name">
					<?php if ( $aSummary[ 'enabled' ] ) : ?>
						<div class="dashicons dashicons-yes"
							 title="Module Active"></div>
					<?php else : ?>
						<div class="dashicons dashicons-warning"
							 title="Module Disabled"></div>
					<?php endif; ?>
					<?php echo $aSummary[ 'name' ]; ?>
				</div>
			</a>
		<?php endforeach; ?>
		</div>
	</div>

    <div class="col" id="ColumnOptions">
		<?php include( $sBaseDirName.'feature-default.php' ); ?>
	</div>
</div>