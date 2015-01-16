<?php
include_once( 'icwp-wpsf-config_header.php' );
?>
	<div class="row">
		<div class="<?php echo $icwp_fShowAds? 'span9' : 'span12'; ?>">

			<form action="<?php echo $icwp_form_action; ?>" method="post" class="form-horizontal">

				<?php if ( isset ( $fAutoupdatesOn ) && $fAutoupdatesOn ) : ?>
					<legend>Run Updates Now</legend>
					<div class="control-group">
						<label class="control-label">Run Automatic Updates
							<br /><span>[<a target="_blank" href="http://icwp.io/44">more info</a>]</span>
						</label>
						<div class="controls">
							<div id="icwp_wpsf_force_run_autoupdates" class="option_section selected_item active">
								<a class="btn btn-warning" href="<?php echo $icwp_form_action; ?>&force_run_auto_updates=now&_wpnonce=<?php echo wp_create_nonce($icwp_nonce_field); ?>&icwp_link_action=1">
									<?php echo _wpsf__('Force Run Automatic Updates Now').' &rarr;';?>
								</a>
								<p class="help-block">WordPress Automatic Updates runs twice daily, but you can run it now on-demand.</p>
							</div>
						</div><!-- controls -->
					</div>
				<?php endif; ?>
				<?php
				wp_nonce_field( $icwp_nonce_field );
				printAllPluginOptionsForm( $icwp_aAllOptions, $icwp_var_prefix, 1 );
				?>
				<div class="form-actions">
					<input type="hidden" name="<?php echo $icwp_var_prefix; ?>all_options_input" value="<?php echo $icwp_all_options_input; ?>" />
					<input type="hidden" name="<?php echo $icwp_var_prefix; ?>plugin_form_submit" value="Y" />
					<button type="submit" class="btn btn-primary" name="submit"><?php _wpsf_e( 'Save All Settings' ); ?></button>
				</div>
			</form>

		</div><!-- / span9 -->

		<?php if ( $icwp_fShowAds ) : ?>
			<div class="span3" id="side_widgets">
				<?php echo getWidgetIframeHtml('side-widgets-wtb'); ?>
			</div>
		<?php endif; ?>
	</div><!-- / row -->

<?php include_once( 'icwp-wpsf-config_footer.php' );