<h2><?php echo $strings['um_current_user_settings']; ?>
	<small>(<?php echo $time_now; ?>)</small>
</h2>
<?php if ( !empty( $aActiveSessions ) ) : ?>
	<table class="table table-bordered">
		<tr>
			<th><?php echo $strings['um_username']; ?></th>
			<th><?php echo $strings['um_logged_in_at']; ?></th>
			<th><?php echo $strings['um_last_activity_at']; ?></th>
			<th><?php echo $strings['um_last_activity_uri']; ?></th>
			<th><?php echo $strings['um_login_ip']; ?></th>
			<th><?php echo $strings['um_login_attempts']; ?></th>
		</tr>
		<?php foreach( $aActiveSessions as $aSessionData ) : ?>
		<tr>
			<td><?php echo $aSessionData['wp_username']; ?></td>
			<td><?php echo $aSessionData['logged_in_at']; ?></td>
			<td><?php echo $aSessionData['last_activity_at']; ?></td>
			<td><?php echo $aSessionData['last_activity_uri']; ?></td>
			<td>
				<a href="http://whois.domaintools.com/<?php echo $aSessionData['ip']; ?>" target="_blank"><?php echo $aSessionData['ip']; ?></a>
			</td>
			<td><?php echo $aSessionData['login_attempts']; ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
<?php else : ?>
	<?php echo $strings['um_need_to_enable_user_management']; ?>
<?php endif; ?>