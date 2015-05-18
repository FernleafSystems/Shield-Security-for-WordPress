<p>
	<?php
	echo sprintf( _wpsf__('Notice - %s'),
		_wpsf__( 'You should know that your IP address is whitelisted and features you activate do not apply to you.' )
	);
	echo '<br/>' . sprintf( _wpsf__( 'Your IP address is: %s' ), $sIpAddress );
	?>
</p>