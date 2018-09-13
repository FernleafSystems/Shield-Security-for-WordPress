<div id="shield-options-backupcode" class="shield-user-options-block">
	<h3><?php echo $strings[ 'title' ]; ?>
		<small>(<?php echo $strings[ 'provided_by' ]; ?>)</small>
	</h3>
	<table class="form-table">
		<tbody>

			<?php if ( $is_my_user_profile ) : ?>
				<tr>
					<th>
						<label>
							<?php echo $strings[ 'label_enter_code' ]; ?>
						</label>
					</th>
					<td>
						<?php if ( !$has_mfa ) : ?>
							<?php echo $strings[ 'not_available' ]; ?>
						<?php else : ?>
							<a href="#" id="IcwpWpsfGenBackupLoginCode"
							   class="button button-primary">
								<?php echo $strings[ 'button_gen_code' ]; ?>
							</a>
							<?php if ( $has_validated_profile ) : ?>
								<a href="#" id="IcwpWpsfDelBackupLoginCode"
								   class="button">
								<?php echo $strings[ 'button_del_code' ]; ?>
							</a>
							<?php endif; ?>
							<p class="description">
								<?php echo $strings[ 'description_code' ]; ?>
								<br/><strong><?php echo $strings[ 'description_code_ext1' ]; ?></strong>
								<?php if ( $has_validated_profile ) : ?>
									<br /><?php echo $strings[ 'description_code_ext2' ]; ?>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			<?php else : ?>
				<td>
					<p class="description"><?php echo $strings[ 'cant_add_other_user' ]; ?></p>
				</td>
			<?php endif; ?>

		</tbody>
	</table>
</div>