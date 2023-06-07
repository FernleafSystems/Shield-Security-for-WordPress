<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;
use FernleafSystems\Wordpress\Services\Utilities\ServiceProviders;

class LicenseScheduleCheck extends LicenseBase {

	use Traits\NonceVerifyNotRequired;
	use Traits\AuthNotRequired;

	public const SLUG = 'license_schedule_check';

	protected function exec() {

		$delay = null;
		try {
			if ( ( new IpID( $this->con()->this_req->ip ) )->run()[ 0 ] === ServiceProviders::PROVIDER_SHIELD ) {
				$delay = 60;
			}
		}
		catch ( \Exception $e ) {
		}

		$this->con()
			 ->getModule_License()
			 ->getLicenseHandler()
			 ->scheduleAdHocCheck( $delay );
		$this->response()->action_response_data = [
			'success' => true,
			'message' => __( 'License Check Scheduled', 'wp-simple-firewall' ),
		];
	}
}