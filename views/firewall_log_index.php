<?php
$sBaseDirName = dirname(__FILE__).ICWP_DS;
include_once( $sBaseDirName.'config_header.php' );

$aLogTypes = array(
	0	=>	_wpsf__('Info'),
	1	=>	_wpsf__('Warning'),
	2	=>	_wpsf__('Critical')
);
?>
	<div class="row">
		<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">
			<form action="<?php echo $icwp_form_action; ?>" method="post" class="form-horizontal">
				<?php
				wp_nonce_field( $icwp_nonce_field );
				?>
				<div class="form-actions">
					<input type="hidden" name="<?php echo $icwp_var_prefix; ?>plugin_form_submit" value="Y" />
					<button type="submit" class="btn btn-primary" name="clear_log_submit" value="1"><?php _wpsf_e( 'Clear/Fix Log' ); ?></button>
				</div>
			</form>

			<?php if ( !$icwp_firewall_log ) : ?>
				<?php echo 'There are currently no logs to display. If you expect there to be some, use the button above to Clean/Fix them.'; ?>
			<?php else : ?>

				<table class="table table-bordered table-hover table-condensed">
					<tr>
						<th><?php _wpsf_e('Message Type'); ?></th>
						<th><?php _wpsf_e('Message'); ?></th>
					</tr>
					<?php foreach( $icwp_firewall_log as $sId => $aLogData ) : ?>
						<tr class="row-log-header">
							<td>IP: <strong><?php echo $aLogData['ip']; ?></strong></td>
							<td colspan="2">
							<span class="cell-section section-ip">
								[ <a href="http://whois.domaintools.com/<?php echo $aLogData['ip']; ?>" target="_blank"><?php _wpsf_e('IPWHOIS Lookup');?></a> ]
								[
								<?php if ( in_array( $aLogData['ip_long'], $icwp_ip_blacklist ) ) : ?>
									<a href="<?php echo $icwp_form_action; ?>&unblackip=<?php echo $aLogData['ip']; ?>&_wpnonce=<?php echo wp_create_nonce($icwp_nonce_field); ?>&icwp_link_action=1"><?php _wpsf_e('Remove From Firewall Blacklist');?></a>
								<?php else: ?>
									<a href="<?php echo $icwp_form_action; ?>&blackip=<?php echo $aLogData['ip']; ?>&_wpnonce=<?php echo wp_create_nonce($icwp_nonce_field); ?>&icwp_link_action=1"><?php _wpsf_e('Add To Firewall Blacklist');?></a>
								<?php endif; ?>
								]
								[
								<?php if ( in_array( $aLogData['ip_long'], $icwp_ip_whitelist ) ) : ?>
									<a href="<?php echo $icwp_form_action; ?>&unwhiteip=<?php echo $aLogData['ip']; ?>&_wpnonce=<?php echo wp_create_nonce($icwp_nonce_field); ?>&icwp_link_action=1"><?php _wpsf_e('Remove From Firewall Whitelist');?></a>
								<?php else: ?>
									<a href="<?php echo $icwp_form_action; ?>&whiteip=<?php echo $aLogData['ip']; ?>&_wpnonce=<?php echo wp_create_nonce($icwp_nonce_field); ?>&icwp_link_action=1"><?php _wpsf_e('Add To Firewall Whitelist');?></a>
								<?php endif; ?>
								]
							</span>
								<span class="cell-section section-timestamp"><?php echo date( 'Y/m/d H:i:s', $aLogData['created_at'] ); ?></span>
							</td>
						</tr>
						<?php
						$aMessages = unserialize( $aLogData['messages'] );
						if ( is_array( $aMessages ) ) {
							foreach( $aMessages as $aLogItem ) :
								list( $sLogType, $sLogMessage ) = $aLogItem;
								?>
								<tr class="row-<?php echo $aLogTypes[$sLogType]; ?>">
									<td class="cell-log-type"><?php echo $aLogTypes[$sLogType] ?></td>
									<td><?php echo esc_attr($sLogMessage); ?></td>
								</tr>
							<?php
							endforeach;
						}
					endforeach; ?>
				</table>

			<?php endif; ?>
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
		tr.row-Info td {
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