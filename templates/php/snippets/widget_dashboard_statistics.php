<h4><?php echo $heading;?></h4>
<table id="tableShieldStatisticsWidget">
	<?php foreach( $keyStats as $keyStat ) : ?>
		<tr>
			<td style="text-align: left">
				<?php echo $keyStat[ 0 ]; ?>
			</td>
			<td style="text-align: right">
				<?php echo $keyStat[ 1 ]; ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>