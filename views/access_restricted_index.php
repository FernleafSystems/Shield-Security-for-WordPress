<?php
$sBaseDirName = dirname(__FILE__).ICWP_DS;
include_once( $sBaseDirName.'config_header.php' );
?>
	<div class="row">
		<div class="span9">
			<?php
			if ( false && isset( $_COOKIE[ 'TODOcookie-name' ] ) ) { //the user hasn't created an encryption salt
				?>
				<div class="alert alert-info">
					<p>You are currently authorized to access your cPanel Manager functions with this plugin.</p>
					<p>You will be returned here once your session times out.</p>
					<form method="post" action="<?php echo $worpit_form_action; ?>" class="form-horizontal">
						<?php wp_nonce_field( $worpit_nonce_field ); ?>
						<input type="hidden" name="cpm_form_submit" value="1" />
						<button type="submit" class="btn btn-primary" name="submit_remove_access">End cPanel Manager Session Now</button>
					</form>
				</div>
			<?php
			}
			else {
				?>
				<div class="well admin_access_restriction_form">
					<h3><?php _wpsf_e( 'What should you enter here?');?></h3>
					<p><?php _wpsf_e( 'At some point you supplied an Admin Access Key - to manage this plugin, you must supply it here first.');?></p>
					<form action="<?php echo $icwp_form_action; ?>" method="post" class="form-horizontal">
						<div class="control-group">
							<label class="control-label" for="<?php echo $icwp_var_prefix; ?>admin_access_key_request"><?php _wpsf_e( 'Enter Access Key');?><br></label>
							<div class="controls">
								<div class="option_section selected_item active" id="option_section_icwp_wpsf_admin_access_key">
									<label>
										<input type="text" name="<?php echo $icwp_var_prefix; ?>admin_access_key_request" value="" autocomplete="off" />
									</label>
									<p class="help-block"><?php _wpsf_e( 'To manage this plugin you must enter the access key.');?></p>
								</div>
							</div><!-- controls -->
						</div>
						<div class="form-actions">
							<?php wp_nonce_field( $icwp_nonce_field ); ?>
							<input type="hidden" name="<?php echo $icwp_var_prefix; ?>plugin_form_submit" value="Y" />
							<button type="submit" class="btn btn-primary" name="submit"><?php _wpsf_e( 'Submit Access Key' ); ?></button>
						</div>
					</form>
				</div>
			<?php
			}
			?>
		</div><!-- / span9 -->
		<div class="span3" id="side_widgets">
			<?php // echo getWidgetIframeHtml( 'cpm-side-widgets' ); ?>
		</div>
	</div>

<?php
include_once( $sBaseDirName.'config_footer.php' );
