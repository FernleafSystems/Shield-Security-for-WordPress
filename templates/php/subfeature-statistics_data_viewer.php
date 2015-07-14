<div class="row">
	<div class="span12">

		<dl>
			<?php foreach ( $aStatsData as $sStatKey => $nCount ) : ?>
				<?php if ( !is_int( $nCount ) ) continue; ?>
				<dt>
					<?php echo $sStatKey; ?>
				</dt>
				<dd>
					<?php echo $nCount; ?>
				</dd>
			<?php endforeach; ?>
		</dl>


</div><!-- / span9 -->
</div><!-- / row -->