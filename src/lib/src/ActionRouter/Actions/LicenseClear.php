<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class LicenseClear extends LicenseBase {

	public const SLUG = 'license_clear';

	protected function exec() {
		$licHandler = self::con()->comps->license;
		$licHandler->deactivate( false );
		$licHandler->clearLicense();
		$this->response()->action_response_data = [
			'success'     => true,
			'message'     => __( 'ShieldPRO License Cleared', 'wp-simple-firewall' ),
			'page_reload' => true,
		];
	}
}