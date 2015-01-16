<?php
include_once( 'icwp-wpsf-config_header.php' );
$icwp_fShowAds = false;
?>
	<div class="row">
		<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">
			<form action="<?php echo $icwp_form_action; ?>" method="post" class="form-horizontal">
				<?php
				wp_nonce_field( $icwp_nonce_field );
				?>
				<div class="form-actions">
					<input type="hidden" name="<?php echo $icwp_var_prefix; ?>plugin_form_submit" value="Y" />
					<button type="submit" class="btn btn-primary" name="clear_log_submit"><?php _wpsf_e( 'Clear/Fix Log' ); ?></button>
				</div>
			</form>

			<?php if ( !$icwp_urlrequests_log ) : ?>
				<?php echo 'There are currently no logs to display. If you expect there to be some, use the button above to Clean/Fix them.'; ?>
			<?php else : ?>

				<table class="table table-bordered table-hover table-condensed">
					<tr>
						<th><?php _wpsf_e('Date'); ?></th>
						<th><?php _wpsf_e('URL'); ?></th>
						<th><?php _wpsf_e('Method'); ?></th>
						<th><?php _wpsf_e('Data'); ?></th>
						<th><?php _wpsf_e('Error?'); ?></th>
					</tr>
					<?php foreach( $icwp_urlrequests_log as $sId => $aLogData ) : ?>
						<tr class="row-log-header">
							<td>
								<span class="cell-section section-timestamp"><?php echo date( 'Y/m/d H:i:s', $aLogData['requested_at'] ); ?></span>
							</td>
							<td><?php echo $aLogData['request_url']; ?></td>
							<td><?php echo $aLogData['request_method']; ?></td>
							<td>
								<dl class="dl-horizontal">
									<?php
									$aArgs = unserialize( $aLogData['request_args'] );
									foreach( $aArgs as $sKey => $mValue ) {
										echo sprintf( '<dt>%s</dt><dd>%s&nbsp;</dd>', $sKey, is_scalar( $mValue )? esc_attr( urldecode($mValue) ) : print_r( $mValue, true ) );
									}
									?>
								</dl>
							</td>
							<td><?php echo $aLogData['is_error']; ?></td>
						</tr>
					<?php endforeach; ?>
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
		dt {
			width: auto !important;
		}
		tr.row-log-header td {
			border-top: 2px solid #999 !important;
		}
		td .cell-section {
			display: inline-block;
		}
		td .section-timestamp {
			text-align: right;
			width: 28%;
		}
	</style>

<?php include_once( 'icwp-wpsf-config_footer.php' );