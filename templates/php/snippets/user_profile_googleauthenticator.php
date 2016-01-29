<style type="text/css">
	#shield_ga_secret {
		letter-spacing: 5px;
		font-family: monospace;
		font-size: 24px;
		text-shadow: 1px 1px 0px rgba(0,0,0,0.4);
		background-color: transparent;
		border: 1px solid rgba(0,0,0,0.1);
		padding: 0 7px;
		background-color: whitesmoke;
	}
</style>
<div id="shield-options-google-authenticator" class="shield-user-options-block">
	<h3><?php echo $strings['title']; ?>
		<small>(<?php echo $strings['provided_by']; ?>)</small>
	</h3>
	<table class="form-table">
		<tbody>

		<?php if ( $user_has_google_authenticator_validated ) : ?>

			<?php if ( $is_my_user_profile || ( $i_am_valid_admin && !$user_to_edit_is_admin ) ) : ?>
				<tr>
					<th><label for="shield_turn_off_google_authenticator"><?php echo $strings['label_check_to_remove']; ?></label></th>
					<td>
						<input type="checkbox" name="shield_turn_off_google_authenticator" id="shield_turn_off_google_authenticator" value="Y" />
						<p class="description">
							<?php echo $strings['description_remove_google_authenticator']; ?>
						</p>
					</td>
				</tr>
				<?php if ( $is_my_user_profile ) : ?>
					<tr>
						<th><label for="shield_ga_otp_code"><?php echo $strings['label_enter_code']; ?></label></th>
						<td>
							<input class="regular-text" type="text" id="shield_ga_otp_code" name="shield_ga_otp_code" value="" autocomplete="off" />
							<p class="description"><?php echo $strings['description_otp_code']; ?></p>
						</td>
					</tr>
				<?php endif; ?>
			<?php else : ?>
				<td>
					<p class="description"><?php echo $strings['sorry_cant_remove_from_to_other_admins']; ?></p>
				</td>
			<?php endif; ?>

		<?php else : ?>

			<?php if ( $is_my_user_profile ) : ?>
				<tr>
					<th><?php echo $strings['label_scan_qr_code']; ?></th>
					<td>
						<img src="<?php echo $chart_url; ?>" />
						<p class="description"><?php echo $strings['description_chart_url']; ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="shield_ga_secret"><?php echo $strings['label_ga_secret']; ?></label></th>
					<td>
						<span id="shield_ga_secret"><?php echo $user_google_authenticator_secret; ?></span>
						<p class="description"><?php echo $strings['description_ga_secret']; ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="shield_ga_otp_code"><?php echo $strings['label_enter_code']; ?></label></th>
					<td>
						<input class="regular-text" type="text" id="shield_ga_otp_code" name="shield_ga_otp_code" value="" autocomplete="off" />
						<p class="description"><?php echo $strings['description_otp_code']; ?></p>
					</td>
				</tr>
			<?php else : ?>
				<td>
					<p class="description"><?php echo $strings['sorry_cant_add_to_other_user']; ?></p>
				</td>
			<?php endif; ?>

		<?php endif; ?>

		</tbody>
	</table>
</div>