<h3>
	<?php
	echo sprintf( _wpsf__('Notice - %s'),
		_wpsf__( 'You should know that your IP address is whitelisted and features you activate do not apply to you.' )
	);
	echo '<br/>' . sprintf( _wpsf__( 'You IP address is: %s' ), $sIpAddress );
	?>
</h3>