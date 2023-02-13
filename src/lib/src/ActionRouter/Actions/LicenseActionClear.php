<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class LicenseActionClear extends LicenseBase {

	public const SLUG = 'license_action_clear';

	protected function exec() {
		$licHandler = $this->getCon()->getModule_License()->getLicenseHandler();
		$licHandler->deactivate( false );
		$licHandler->clearLicense();
		$this->response()->action_response_data = [
			'success' => true,
			'message' => __( 'ShieldPRO License Cleared', 'wp-simple-firewall' ),
		];
	}
}