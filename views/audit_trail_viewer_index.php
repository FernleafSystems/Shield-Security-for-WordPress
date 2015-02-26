<?php
$sBaseDirName = dirname(__FILE__).ICWP_DS;
include_once( $sBaseDirName.'config_header.php' );

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
				<td><?php echo date( 'Y/m/d', $aAuditEntry['created_at'] ).'<br />'.date( 'H:i:s', $aAuditEntry['created_at'] ); ?></td>
				<td><?php echo $aAuditEntry['event']; ?></td>
				<td><?php echo $aAuditEntry['message']; ?></td>
				<td><?php echo $aAuditEntry['wp_username']; ?></td>
				<td><?php echo $aAuditEntry['category']; ?></td>
				<td>
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
		<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">

			<?php printAuditTrailTable( _wpsf__( 'WordPress Simple Firewall' ), $icwp_aAuditDataWpsf, $icwp_nYourIp ); ?>
			<?php printAuditTrailTable( _wpsf__( 'Users' ), $icwp_aAuditDataUsers, $icwp_nYourIp ); ?>
			<?php printAuditTrailTable( _wpsf__( 'Plugins' ), $icwp_aAuditDataPlugins, $icwp_nYourIp ); ?>
			<?php printAuditTrailTable( _wpsf__( 'Themes' ), $icwp_aAuditDataThemes, $icwp_nYourIp ); ?>
			<?php printAuditTrailTable( _wpsf__( 'WordPress' ), $icwp_aAuditDataWordpress, $icwp_nYourIp ); ?>
			<?php printAuditTrailTable( _wpsf__( 'Posts' ), $icwp_aAuditDataPosts, $icwp_nYourIp ); ?>
			<?php printAuditTrailTable( _wpsf__( 'Emails' ), $icwp_aAuditDataEmails, $icwp_nYourIp ); ?>

		</div><!-- / span9 -->

		<?php if ( $icwp_fShowAds ) : ?>
			<div class="span3" id="side_widgets">
				<?php echo getWidgetIframeHtml('side-widgets-wtb'); ?>
			</div>
		<?php endif; ?>
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
		table.table.table-audit_trail {
			border: 2px solid #777777;
			margin-bottom: 40px;
		}
		th.cell-time {
			width: 90px;
			max-width: 90px;
		}
		th.cell-username {
			width: 120px;
			max-width: 120px;
		}
		th.cell-event {
			width: 150px;
			max-width: 150px;
		}
		th.cell-category {
			width: 80px;
			max-width: 80px;
		}
		th.cell-message {
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

<?php
include_once( $sBaseDirName.'config_footer.php' );