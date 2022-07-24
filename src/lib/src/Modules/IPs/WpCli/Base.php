<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;

class Base extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function checkList( string $list ) {
		if ( !in_array( $list, [ 'white', 'bypass', 'black', 'block' ] ) ) {
			throw new \Exception( sprintf( '%s %s',
				sprintf( __( "'%s' is an unsupported IP list.", 'wp-simple-firewall' ), $list ),
				sprintf( __( 'Please use one of %s.', 'wp-simple-firewall' ), "'bypass' or 'white'; 'block' or 'black'" )
			) );
		}
	}
}