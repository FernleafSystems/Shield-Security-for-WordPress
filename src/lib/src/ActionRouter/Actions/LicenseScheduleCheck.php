<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class LicenseScheduleCheck extends LicenseBase {

	use Traits\NonceVerifyNotRequired;
	use Traits\AuthNotRequired;

	public const SLUG = 'license_check';

	protected function exec() {
		$this->getCon()
			 ->getModule_License()
			 ->getLicenseHandler()
			 ->scheduleAdHocCheck();
		$this->response()->action_response_data = [
			'success' => true,
			'message' => __( 'License Check Scheduled', 'wp-simple-firewall' ),
		];
	}
}