<div class="row">
	<div class="span12" id="AuditTrailTabs">

		<ul class="nav nav-tabs" id="AuditTrailTabs">
		<?php foreach ( $aAuditTables as $sContext => $aAuditDataContext ) : ?>
			<li><a href="#Context<?php echo $sContext; ?>" data-toggle="tab">
					<?php echo $aContexts[ $sContext ]; ?>
				</a></li>
		<?php endforeach; ?>
		</ul>
		<div class="tab-content">
			<?php foreach ( $aAuditTables as $sContext => $aAuditDataContext ) : ?>
				<div class="tab-pane <?php echo !$sContext ? 'active' : '' ?>" id="Context<?php echo $sContext; ?>">
				<?php echo $aAuditDataContext; ?>
				</div>
			<?php endforeach; ?>
		</div>

	</div><!-- / span9 -->
</div><!-- / row -->

<script>
  jQuery( function () {
	  jQuery( '#AuditTrailTabs > ul a:first' ).tab( 'show' );
  } )
</script>