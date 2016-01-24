<div id="shield-options-google-authenticator">
	<h3 id="shield-googleauthenticator"><?php echo $strings['title']; ?></h3>

	<table class="form-table">
		<tbody>
		<?php if ( !isset( $user_has_google_authenticator_validated ) || !$user_has_google_authenticator_validated ) : ?>
			<tr>
				<th>Scan This QR Code</th>
				<td>
					<img src="<?php echo $chart_url; ?>" />
					<p class="description"><?php echo $strings['description_chart_url']; ?></p>
				</td>
			</tr>
		<?php else : ?>
			<tr>
				<th><label for="shield_turn_off_google_authenticator"><?php echo $strings['label_check_to_remove']; ?></label></th>
				<td>
					<input type="checkbox" name="shield_turn_off_google_authenticator" id="shield_turn_off_google_authenticator" value="Y" />
				</td>
			</tr>
		<?php endif; ?>
		<tr>
			<th><label for="shield_ga_otp_code"><?php echo $strings['label_enter_code']; ?></label></th>
			<td>
				<input class="regular-text" type="text" id="shield_ga_otp_code" name="shield_ga_otp_code" value="">
				<p class="description"><?php echo $strings['description_otp_code']; ?></p>
			</td>
		</tr>
		</tbody>
	</table>
</div>