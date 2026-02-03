<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless;
use FernleafSystems\Wordpress\Services\Utilities\Licenses\Keyless\Ping;

class LicenseCheckDebug extends LicenseBase {

	public const SLUG = 'license_debug';

	protected function exec() {
		$success = ( new Ping() )->ping();
		$host = wp_parse_url( Keyless\Base::DEFAULT_URL_STUB, PHP_URL_HOST );

		if ( $success ) {
			$msg = __( 'Successfully connected to license server.', 'wp-simple-firewall' );
		}
		elseif ( !Services::IP()->isValidIp( gethostbyname( $host ) ) ) {
			$msg = sprintf( __( 'Could not resolve host IP address: %s', 'wp-simple-firewall' ), $host );
		}
		else {
			$msg = __( 'Failed to connect to license server.', 'wp-simple-firewall' );
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}
