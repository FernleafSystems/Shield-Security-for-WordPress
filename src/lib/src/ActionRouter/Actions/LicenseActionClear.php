<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;

class LicenseActionClear extends LicenseBase {

	public const SLUG = 'license_action_clear';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$licHandler = $mod->getLicenseHandler();
		$licHandler->deactivate( false );
		$licHandler->clearLicense();
		$this->response()->action_response_data = [
			'success' => true,
			'message' => __( 'ShieldPRO License Cleared', 'wp-simple-firewall' ),
		];
	}
}