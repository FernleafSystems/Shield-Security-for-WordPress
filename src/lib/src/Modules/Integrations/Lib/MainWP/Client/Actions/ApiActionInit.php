<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class ApiActionInit {

	use ModConsumer;

	public function run( string $action ) :array {

		switch ( $action ) {
			case 'license_check':
				$valid = $this->getCon()
							  ->getModule_License()
							  ->getLicenseHandler()
							  ->verify()
							  ->hasValidWorkingLicense();
				$response = [
					'success' => $valid,
					'message' => $valid ? __( 'ShieldPRO license verified', 'wp-simple-firewall' )
						: __( "ShieldPRO license couldn't be found", 'wp-simple-firewall' )
				];
				break;

			default:
				$response = [
					'success' => false,
					'message' => 'Not a supported Shield+MainWP Site Action',
				];
				break;
		}

		return $response;
	}
}