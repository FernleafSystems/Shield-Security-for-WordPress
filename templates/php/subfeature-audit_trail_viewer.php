<div class="row">
	<div class="span12">

		<?php if ( !empty( $aAudits ) && is_array( $aAudits ) ) : ?>
			<ul class="nav nav-tabs">
			<?php foreach ( $aAudits as $nKey => $aAuditDataContext ) : ?>
				<li><a href="#Context<?php echo $nKey; ?>" data-toggle="tab"><?php echo $aAuditDataContext['title']; ?></a></li>
			<?php endforeach; ?>
			</ul>
			<div class="tab-content">
				<?php foreach ( $aAudits as $nKey => $aAuditDataContext ) : ?>
					<div class="tab-pane <?php echo !$nKey ? 'active' : '' ?>" id="Context<?php echo $nKey; ?>">

						<table class="table table-hover table-striped">
							<tr>
								<th class="cell-time"><?php echo $strings['at_time']; ?></th>
								<th class="cell-event"><?php echo $strings['at_event']; ?></th>
								<th class="cell-message"><?php echo $strings['at_message']; ?></th>
								<th class="cell-username"><?php echo $strings['at_username']; ?></th>
								<th class="cell-category"><?php echo $strings['at_category']; ?></th>
								<th class="cell-ip"><?php echo $strings['at_ipaddress']; ?></th>
							</tr>
							<?php foreach ( $aAuditDataContext['trail'] as $aAuditData ) : ?>
								<tr class="<?php echo ( $nYourIp == $aAuditData['ip'] ) ? 'your-ip' : 'not-your-ip'; ?>">
									<td class="cell-time"><?php echo $aAuditData['created_at']; ?></td>
									<td class="cell-event"><?php echo $aAuditData['event']; ?></td>
									<td class="cell-message"><?php echo $aAuditData['message']; ?></td>
									<td class="cell-username"><?php echo $aAuditData['wp_username']; ?></td>
									<td class="cell-category"><?php echo $aAuditData['category']; ?></td>
									<td class="cell-ip">
										<?php echo $aAuditData['ip']; ?>
										<br/><strong><?php echo ( $nYourIp == $aAuditData['ip'] ) ? $strings['at_you'] : ''; ?></strong>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>
				<?php endforeach; ?>
			</div>

		<?php else : ?>
			<?php echo $strings['at_no_audit_entries']; ?>
		<?php endif; ?>

	</div><!-- / span9 -->
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
	tr.not-your-ip td {

	}
	tr.your-ip td {
		opacity: 0.6;
	}
</style>