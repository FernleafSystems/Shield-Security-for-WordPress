<div class="row">
	<div class="span9">
		<?php if ( !$flags[ 'has_session' ] ) : ?>
			<p>This plugin has been locked-down by a Security Administrator.
			   To authenticate with the Security Admin system you need a valid user session
			   (which you don't have at the moment). Please log out of WordPress and re-login again.</p>
		<?php else : ?>
		<div class="well admin_access_restriction_form">
			<h3><?php echo $strings[ 'aar_what_should_you_enter' ]; ?></h3>
			<p><?php echo $strings[ 'aar_must_supply_key_first' ]; ?></p>
			<form action="<?php echo $form_action; ?>" method="post" class="form-horizontal">
				<div class="control-group">
					<label class="control-label" for="admin_access_key_request">
						<?php echo $strings[ 'aar_enter_access_key' ]; ?><br>
					</label>
					<div class="controls">
						<div class="option_section selected_item active" id="option_section_icwp_wpsf_admin_access_key">
							<label>
								<input type="password" name="admin_access_key_request"
									   value="" autocomplete="off" autofocus />
							</label>
							<p class="help-block"><?php echo $strings[ 'aar_to_manage_must_enter_key' ]; ?></p>
						</div>
					</div>
				</div>
				<div class="form-actions">
					<?php echo $nonce_field; ?>
					<input type="hidden" name="mod_slug" value="<?php echo $data[ 'mod_slug' ]; ?>" />
					<input type="hidden" name="plugin_form_submit" value="Y" />
					<button type="submit" class="btn btn-success"
							name="submit"><?php echo $strings[ 'aar_submit_access_key' ]; ?></button>
				</div>
			</form>
		</div>
		<?php endif; ?>
	</div>
</div>