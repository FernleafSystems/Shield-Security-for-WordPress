<div id="WpsfWhiteList" style="display:none;">
	<div class="bootstrap-wpadmin wpsf-ip-list" id="IpTableList">
		<?php include( dirname(__FILE__).ICWP_DS.'ip_list_table.php'); ?>
	</div>
</div>

<div id="WpsfAutoBlackList" style="display:none;">
	<div class="bootstrap-wpadmin wpsf-ip-list" id="IpTableList">
		<?php include( dirname(__FILE__).ICWP_DS.'ip_list_table.php'); ?>
	</div>
</div>

<style>
	.wpsf-ip-list table {
		margin: 15px 0;
	}
	.wpsf-ip-list .spinner {
		background: rgba(0, 0, 0, 0) url("images/spinner.gif") no-repeat scroll 0 0 / 20px 20px;
		height: 20px;
		margin: 100px 45%;
		text-align: center;
		vertical-align: middle;
		visibility: visible;
		width: 20px;
	}
</style>

<h3>IP Lists Management</h3>
<p>Click the button below to manage the automatic black list IP addressses</p>
<p>
	<!--<a href="#TB_inline?width=800&height=600&inlineId=WpsfWhiteList" title="White List" class="thickbox btn btn-success">View White List</a>-->
	<a href="#TB_inline?width=800&height=600&inlineId=WpsfAutoBlackList" title="Automatic Black List" class="thickbox btn btn-warning btn-large">View Auto Black List</a>
</p>