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
			$msg = 'Successfully connected to license server.';
		}
		elseif ( !Services::IP()->isValidIp( gethostbyname( $host ) ) ) {
			$msg = sprintf( 'Could not resolve host IP address: %s', $host );
		}
		else {
			$msg = 'Failed to connect to license server.';
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}