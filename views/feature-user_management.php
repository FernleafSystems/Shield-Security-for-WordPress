<?php include_once( $sBaseDirName . 'feature-default.php' );

function printSessionTable( $aSessionsData ) {
	?>
	<table class="table table-bordered">
		<tr>
			<th><?php _wpsf_e('Username'); ?></th>
			<th><?php _wpsf_e('Logged In At'); ?></th>
			<th><?php _wpsf_e('Last Activity At'); ?></th>
			<th><?php _wpsf_e('Last Activity URI'); ?></th>
			<th><?php _wpsf_e('Login IP'); ?></th>
			<th><?php _wpsf_e('Login Attempts'); ?></th>
		</tr>
		<?php foreach( $aSessionsData as $aSessionData ) : ?>
			<tr>
				<td><?php echo $aSessionData['wp_username']; ?></td>
				<td><?php echo date( 'Y/m/d H:i:s', $aSessionData['logged_in_at'] ); ?></td>
				<td><?php echo date( 'Y/m/d H:i:s', $aSessionData['last_activity_at'] ); ?></td>
				<td><?php echo $aSessionData['last_activity_uri']; ?></td>
				<td>
					<a href="http://whois.domaintools.com/<?php echo $aSessionData['ip']; ?>" target="_blank">
						<?php echo $aSessionData['ip']; ?>
					</a>
				</td>
				<td><?php echo $aSessionData['login_attempts']; ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
<?php
}

?>
<div class="row">
	<div class="span12">
		<h2><?php _wpsf_e('Current User Sessions'); ?>
			<small>(<?php echo sprintf( _wpsf__( 'now: %s' ), date( 'Y/m/d H:i:s', time() ) ) ?>)</small>
		</h2>
		<?php if ( !empty($aActiveSessions) ) : ?>
			<?php printSessionTable($aActiveSessions); ?>
		<?php else : ?>
			<?php _wpsf_e('You need to enable the User Management feature to view and manage user sessions.'); ?>
		<?php endif; ?>
	</div>
</div>
<div class="row">
	<div class="span12">
		<h2><?php _wpsf_e('Failed or Pending User Sessions');?> (48hrs)</h2>
		<?php if ( !empty($aFailedSessions) ) : ?>
			<?php printSessionTable($aFailedSessions); ?>
		<?php else : ?>
			<?php _wpsf_e('There are currently no failed or pending sessions to review.'); ?>
		<?php endif; ?>
	</div>
</div>