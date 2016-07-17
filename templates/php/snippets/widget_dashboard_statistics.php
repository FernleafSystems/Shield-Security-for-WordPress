<h4><?php echo $sHeading;?></h4>
<table id="tableShieldStatisticsWidget">
	<?php foreach( $aKeyStats as $sKey => $aKeyStat ) : ?>
		<tr>
			<td style="text-align: left">
				<?php echo $aKeyStat[ 0 ]; ?>
			</td>
			<td style="text-align: right">
				<?php echo $aKeyStat[ 1 ]; ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>