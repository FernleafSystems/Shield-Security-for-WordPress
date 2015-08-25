<?php
$sTableId = 'IpTable' . uniqid();
?>

<?php if ( empty( $auto_black_list ) ) : ?>
	<p id="<?php echo $sTableId; ?>">
		There are no IPs on this list.
		<a href="javascript:refresh_list();">Refresh</a>
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
		<tr>
			<td colspan="4">
				<a href="javascript:refresh_list();">Refresh</a>
			</td>
		</tr>
	</table>
<?php endif; ?>

<script type="text/javascript" >
	function refresh_list() {

		var data = {
			'action': 'icwp_wpsf_GetIpList',
			'_ajax_nonce': '<?php echo $sAjaxNonce; ?>'
		};

		request_and_reload(data);
	}
	function remove_ip( $sIp, $sList ) {

		var data = {
			'action': 'icwp_wpsf_RemoveIpFromList',
			'ip': $sIp,
			'list': $sList,
			'_ajax_nonce': '<?php echo $sAjaxNonce; ?>'
		};

		request_and_reload(data);
	}

	function request_and_reload( requestData ) {

		$oTable = jQuery('#<?php echo $sTableId; ?>');
		$sContentDiv = $oTable.parent();

		$sContentDiv.html( '<div class="spinner"></div>');
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, requestData, function(response) {
			$sContentDiv.html(response);
		});
	}
</script>