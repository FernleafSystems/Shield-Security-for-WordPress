<?php
$sTableId = 'IpTable' . uniqid();
?>

<?php if ( empty( $auto_black_list ) ) : ?>
	<p>
		There are no IPs on this list.
	</p>
<?php else: ?>
	<table class="table table-bordered" id="<?php echo $sTableId; ?>">
		<tr>
			<th>IP Address</th>
			<th>Transgressions</th>
			<th>Last Access</th>
			<th>Actions</th>
		</tr>
		<?php foreach( $auto_black_list as $aIpData ) : ?>
			<tr>
				<td><?php echo $aIpData['ip']; ?></td>
				<td><?php echo $aIpData['transgressions']; ?></td>
				<td><?php echo $aIpData['last_access_at']; ?></td>
				<td><a href="javascript:remove_ip( '<?php echo $aIpData['ip']; ?>', '<?php echo $aIpData['list']; ?>' );">Remove</a></td>
			</tr>
		<?php endforeach; ?>
	</table>

	<script type="text/javascript" >
		function remove_ip( $sIp, $sList ) {

			$oTable = jQuery('table#<?php echo $sTableId; ?>');
			$sContentDiv = $oTable.parent();

			var data = {
				'action': 'icwp_wpsf_RemoveIpFromList',
				'ip': $sIp,
				'list': $sList,
				'_ajax_nonce': '<?php echo $sAjaxNonce; ?>',
				'whatever': 1234
			};

			$sContentDiv.html( '<div class="spinner"></div>');
			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {
				$sContentDiv.html(response);
			});
		}
	</script>
<?php endif; ?>