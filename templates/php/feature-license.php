<?php
/** @var string[] $strings */
?>
<style>
	#wpcontent {
		padding-left: 0;
	}

	.update-nag {
		display:none;
	}

	#ShieldCentralFrame {
		width: 99%;
		margin: 0.4%;
		overflow: hidden;
		border: 1px solid #aaaaaa;
	}
</style>
	<div class="row-fluid">
		<div class="span12 well">
			<h1>License Summary</h1>
			<table style="width: 600px;">
				<tr>
					<td><?php echo $strings[ 'product_name' ]; ?>:</td>
					<td><?php echo $vars[ 'product_name' ]; ?></td>
				</tr>
				<tr>
					<td><?php echo $strings[ 'license_key' ]; ?>:</td>
					<td><?php echo $vars[ 'license_key' ]; ?></td>
				</tr>
				<tr>
					<td><?php echo $strings[ 'license_active' ]; ?>:</td>
					<td><?php echo $vars[ 'license_active' ]; ?></td>
				</tr>
				<tr>
					<td><?php echo $strings[ 'license_status' ]; ?>:</td>
					<td><?php echo $vars[ 'license_status' ]; ?></td>
				</tr>
				<tr>
					<td><?php echo $strings[ 'license_expires' ]; ?>:</td>
					<td><?php echo $vars[ 'license_expires' ]; ?></td>
				</tr>
				<tr>
					<td><?php echo $strings[ 'license_email' ]; ?>:</td>
					<td><?php echo $vars[ 'license_email' ]; ?></td>
				</tr>
			</table>
		</div>
	</div>