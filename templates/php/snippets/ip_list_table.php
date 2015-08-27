<?php
$sTableId = uniqid('IpTable').rand(0,1000);
?>

<table class="table table-bordered" id="<?php echo $sTableId; ?>">
	<?php if ( empty( $list_data ) ) : ?>
		<tr>
			<td colspan="4">
				There are no IPs on this list.
				<a href="javascript:refresh_list( '<?php echo $list_id; ?>', jQuery('#<?php echo $sTableId; ?>').parent() );">Refresh</a>
			</td>
		</tr>
	<?php else: ?>

		<tr>
			<th>IP Address</th>

			<?php if ( $list_id == 'MW' ) : ?>
				<th>Label</th>
			<?php else: ?>
				<th>Transgressions</th>
			<?php endif; ?>

			<th>Last Access</th>
			<th>Actions</th>
		</tr>

		<?php foreach( $list_data as $aIpData ) : ?>
			<tr>
				<td><?php echo $aIpData['ip']; ?></td>
				<?php if ( $list_id == 'MW' ) : ?>
					<td><?php echo $aIpData['label']; ?></td>
				<?php else: ?>
					<td><?php echo $aIpData['transgressions']; ?></td>
				<?php endif; ?>
				<td><?php echo $aIpData['last_access_at']; ?></td>
				<td><a href="javascript:remove_ip( '<?php echo $aIpData['ip']; ?>', '<?php echo $aIpData['list']; ?>' );">Remove</a></td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<td colspan="4">
				<a href="javascript:refresh_list( '<?php echo $list_id; ?>', jQuery('#<?php echo $sTableId; ?>').parent() );">Refresh</a>
			</td>
		</tr>

	<?php endif; ?>

	<?php if ( $list_id == 'MW' ) : ?>
		<tr>
			<td colspan="4">
				<div class="input-append">
					<input class="span3 on_return_send" name="new_ip" placeholder="Add IP Address" id="AddIpAddress" type="text" />
					<input class="span3 on_return_send" name="new_ip" placeholder="Label" id="AddIpAddressLabel" type="text" />
					<button class="btn" type="button" id="AddIpButton">Click To Add!</button>
				</div>
			</td>
		</tr>
	<?php endif; ?>

</table>

<script type="text/javascript" >

	$oTable = jQuery('#<?php echo $sTableId; ?>');

	jQuery( document ).ready(function() {
		jQuery( '#AddIpButton', $oTable ).click( add_ip_to_whitelist );
		jQuery( 'input.on_return_send', $oTable ).keypress(function(e) {
			if(e.which == 13) {
				add_ip_to_whitelist();
			}
		});
	});

	function remove_ip( $sIp, $sList ) {

		var aData = {
			'action': 'icwp_wpsf_RemoveIpFromList',
			'ip': $sIp,
			'list': $sList,
			'_ajax_nonce': '<?php echo $sAjaxNonce; ?>'
		};

		$oContentDiv = $oTable.parent();

		request_and_reload( aData, $oContentDiv );
	}

	function add_ip_to_whitelist( $sList ) {
		$oTable = jQuery('#<?php echo $sTableId; ?>');

		var aData = {
			'action': 'icwp_wpsf_AddIpToWhiteList',
			'ip': jQuery('#AddIpAddress', $oTable).val(),
			'label': jQuery('#AddIpAddressLabel', $oTable).val(),
			'list': 'MW',
			'_ajax_nonce': '<?php echo $sAjaxNonce; ?>'
		};

		$oContentDiv = $oTable.parent();

		request_and_reload( aData, $oContentDiv );
	}

</script>