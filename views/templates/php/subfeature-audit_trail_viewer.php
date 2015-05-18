<?php
function printAuditTrailTable( $sTitle, $aAuditData, $nYourIp = -1 ) {

	?><h4 class="table-title"><?php echo $sTitle; ?></h4><?php

	if ( empty( $aAuditData ) ) {
		_wpsf_e( 'There are currently no audit entries this is section.' );
		return;
	}
	?>
	<table class="table table-hover table-striped table-audit_trail">
		<tr>
			<th class="cell-time"><?php _wpsf_e('Time'); ?></th>
			<th class="cell-event"><?php _wpsf_e('Event'); ?></th>
			<th class="cell-message"><?php _wpsf_e('Message'); ?></th>
			<th class="cell-username"><?php _wpsf_e('Username'); ?></th>
			<th class="cell-category"><?php _wpsf_e('Category'); ?></th>
			<th class="cell-ip"><?php _wpsf_e('IP Address'); ?></th>
		</tr>
		<?php foreach( $aAuditData as $aAuditEntry ) : ?>
			<tr>
				<td class="cell-time"><?php echo $aAuditEntry['created_at']; ?></td>
				<td class="cell-event"><?php echo $aAuditEntry['event']; ?></td>
				<td class="cell-message"><?php echo $aAuditEntry['message']; ?></td>
				<td class="cell-username"><?php echo $aAuditEntry['wp_username']; ?></td>
				<td class="cell-category"><?php echo $aAuditEntry['category']; ?></td>
				<td class="cell-ip">
					<?php echo $aAuditEntry['ip']; ?>
					<?php echo ( $nYourIp == $aAuditEntry['ip'] ) ? '<br />('._wpsf__('You').')' : ''; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php
}
?>
<div class="row">
	<div class="span12">

		<?php printAuditTrailTable( _wpsf__( 'WordPress Simple Firewall' ), $aAuditDataWpsf, $nYourIp ); ?>
		<?php printAuditTrailTable( _wpsf__( 'Users' ), $aAuditDataUsers, $nYourIp ); ?>
		<?php printAuditTrailTable( _wpsf__( 'Plugins' ), $aAuditDataPlugins, $nYourIp ); ?>
		<?php printAuditTrailTable( _wpsf__( 'Themes' ), $aAuditDataThemes, $nYourIp ); ?>
		<?php printAuditTrailTable( _wpsf__( 'WordPress' ), $aAuditDataWordpress, $nYourIp ); ?>
		<?php printAuditTrailTable( _wpsf__( 'Posts' ), $aAuditDataPosts, $nYourIp ); ?>
		<?php printAuditTrailTable( _wpsf__( 'Emails' ), $aAuditDataEmails, $nYourIp ); ?>

	</div><!-- / span9 -->
</div><!-- / row -->

<div class="row">
	<div class="span6">
	</div><!-- / span6 -->
	<div class="span6">
		<p></p>
	</div><!-- / span6 -->
</div><!-- / row -->
<style>

	h4.table-title {
		font-size: 20px;
		margin: 20px 0 10px 5px;
	}
	th {
		background-color: white;
	}

	tr.row-Warning td {
		background-color: #F2D5AE;
	}
	tr.row-Critical td {
		background-color: #DBAFB0;
	}
	tr.row-log-header td {
		border-top: 2px solid #999 !important;
	}
	td.cell-log-type {
		text-align: right !important;
	}
	td .cell-section {
		display: inline-block;
	}
	td .section-ip {
		width: 68%;
	}
	td .section-timestamp {
		text-align: right;
		width: 28%;
	}
</style>