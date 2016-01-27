<div id="shield-options-email-authentication" class="shield-user-options-block">
	<h3><?php echo $strings['title']; ?>
		<small>(<?php echo $strings['provided_by']; ?>)</small>
	</h3>

	<table class="form-table">
		<tbody>

		<tr>
			<th><label for="shield_email_authentication"><?php echo $strings['label_email_authentication']; ?></label></th>
			<td>
				<input
					type="checkbox"
					name="shield_email_authentication" id="shield_email_authentication"
					value="Y"
					<?php if ( $bools['checked'] ) : ?>
						checked="checked"
					<?php endif; ?>
					<?php if ( $bools['disabled'] ) : ?>
						disabled="disabled"
					<?php endif; ?>
				/>
				<p class="description"><?php echo $strings['description_email_authentication_checkbox']; ?></p>
			</td>
		</tr>

		</tbody>
	</table>
</div>