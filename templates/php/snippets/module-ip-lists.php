
<h2>IP Lists Management</h2>
<p>Click the buttons below to manage the respective IP Address Lists</p>
<p>
	<!--<a href="#TB_inline?width=800&height=600&inlineId=WpsfWhiteList" title="White List" class="thickbox btn btn-success">View White List</a>-->
	<a href="#TB_inline?width=1000&height=600&inlineId=WpsfAutoBlackList" title="Automatic Black List" class="thickbox btn btn-warning btn-large">Manage: Auto Black List</a>
	<a href="#TB_inline?width=1000&height=600&inlineId=WpsfWhiteList" title="White List" class="thickbox btn btn-info btn-large">Manage: White List</a>
</p>

<div id="WpsfWhiteList" style="display:none;">
	<div class="bootstrap-wpadmin wpsf-ip-list" id="IpTableList_White"> </div>
</div>

<div id="WpsfAutoBlackList" style="display:none;">
	<div class="bootstrap-wpadmin wpsf-ip-list" id="IpTableList_AutoBlack"> </div>
</div>
<script type="text/javascript" >
	jQuery( document ).ready(function() {
		refresh_list( 'MW', jQuery('#IpTableList_White') );
		refresh_list( 'AB', jQuery('#IpTableList_AutoBlack') );
	});

	function refresh_list( $sList, $oTarget ) {

		var aData = {
			'action': 'icwp_wpsf_GetIpList',
			'list': $sList,
			'_ajax_nonce': '<?php echo $sAjaxNonce; ?>'
		};

		request_and_reload( aData, $oTarget );
	}

	function remove_ip( $sIp, $sList, $oTarget ) {

		var aData = {
			'action': 'icwp_wpsf_RemoveIpFromList',
			'ip': $sIp,
			'list': $sList,
			'_ajax_nonce': '<?php echo $sAjaxNonce; ?>'
		};

		request_and_reload( aData, $oTarget );
	}

	function request_and_reload( requestData, $oTarget ) {

		$oTarget.html( '<div class="spinner"></div>');
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		jQuery.post(ajaxurl, requestData, function( oResponse ) {

			if( oResponse.data ) {
				$oTarget.html( oResponse.data.html );
			}
			else {
				$oTarget.html( 'There was an unknown error' );
			}

		});
	}
</script>

<style>
	.wpsf-ip-list table {
		margin: 15px 0;
	}
	.wpsf-ip-list table td.centered {
		text-align: center;
	}
	.wpsf-ip-list table td input,
	.wpsf-ip-list table td button {
		margin: 5px 6px;
	}
	.wpsf-ip-list .spinner {
		background: rgba(0, 0, 0, 0) <?php echo includes_url("images/spinner.gif"); ?> no-repeat scroll 0 0 / 20px 20px;
		height: 20px;
		margin: 100px 45%;
		text-align: center;
		vertical-align: middle;
		visibility: visible;
		width: 20px;
	}
</style>