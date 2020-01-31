<div id="shield-options-google-authenticator" class="shield-user-options-block">
	<h3><?php echo $strings[ 'title' ]; ?>
		<small>(<?php echo $strings[ 'provided_by' ]; ?>)</small>
	</h3>
	<table class="form-table">
		<tbody>

		<?php if ( $has_validated_profile ) : ?>

			<?php if ( $is_my_user_profile || $i_am_valid_admin ) : ?>
				<tr>
                    <th><label for="yubi_code"><?php echo $strings[ 'label_enter_code' ]; ?></label></th>
                    <td>
                        <input class="regular-text" name="yubi_code" id="yubi_code"
							   type="text" value="<?php echo $data[ 'secret' ]; ?>" readonly />
                        <p class="description">
							<?php echo $strings[ 'description_otp_code' ]; ?>
							<br /><?php echo $strings[ 'description_otp_code_ext' ]; ?>
						</p>
                    </td>
                </tr>

				<tr>
                    <th><label for="<?php echo $data[ 'otp_field_name' ]; ?>"><?php echo $strings[ 'label_enter_otp' ]; ?></label></th>
                    <td>
                        <input class="regular-text"
							   type="text"
							   id="<?php echo $data[ 'otp_field_name' ]; ?>"
							   name="<?php echo $data[ 'otp_field_name' ]; ?>"
							   value="" autocomplete="off" />
                        <p class="description"><?php echo $strings[ 'description_otp' ]; ?>
							<br /><?php echo $strings[ 'description_otp_ext' ]; ?>
							<br /><?php echo $strings[ 'description_otp_ext_2' ]; ?>
                        </p>
                    </td>
                </tr>
			<?php else : ?>
				<td>
                    <p class="description"><?php echo $strings[ 'cant_remove_admins' ]; ?></p>
                </td>
			<?php endif; ?>

		<?php else : ?>

			<?php if ( $is_my_user_profile ) : ?>
				<tr>
					<th><label for="<?php echo $data[ 'otp_field_name' ]; ?>"><?php echo $strings[ 'label_enter_otp' ]; ?></label></th>
					<td>
						<input class="regular-text"
							   type="text"
							   id="<?php echo $data[ 'otp_field_name' ]; ?>"
							   name="<?php echo $data[ 'otp_field_name' ]; ?>"
							   value="" autocomplete="off" />
						<p class="description">
							<?php echo $strings[ 'description_otp' ]; ?>
							<br /><?php echo $strings[ 'description_otp_ext' ]; ?>
                        </p>
					</td>
				</tr>
			<?php else : ?>
				<td>
					<p class="description"><?php echo $strings[ 'cant_add_other_user' ]; ?></p>
				</td>
			<?php endif; ?>

		<?php endif; ?>

		</tbody>
	</table>
</div>