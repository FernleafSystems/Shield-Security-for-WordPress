<h3 id="shield-googleauthenticator">Google Authenticator</h3>

<table class="form-table">
	<tbody>
	<?php if ( !isset( $user_has_google_authenticator_validated ) || !$user_has_google_authenticator_validated ) : ?>
		<tr>
			<th>Scan This QR Code</th>
			<td><img src="<?php echo $chart_url; ?>" /></td>
		</tr>

		<tr>
			<th>
				<label for="shield_qr_code_otp">Enter Code From App</label>
			</th>
			<td>
				<input class="regular-text" type="text" id="shield_qr_code_otp" name="shield_qr_code_otp" value="">
			</td>
		</tr>
	<?php else : ?>
		<tr>
			<th>
				<label for="shield_turn_off_google_authenticator">Check To Remove Google Authenticator</label>
			</th>
			<td>
				<input type="checkbox" name="shield_turn_off_google_authenticator" value="Y" />
			</td>
		</tr>
	<?php endif; ?>
	</tbody>
</table>