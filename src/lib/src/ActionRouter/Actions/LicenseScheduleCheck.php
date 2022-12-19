<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\License\ModCon;

class LicenseScheduleCheck extends LicenseBase {

	use Traits\NonceVerifyNotRequired;
	use Traits\AuthNotRequired;

	public const SLUG = 'license_check';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$mod->getLicenseHandler()->scheduleAdHocCheck();
		$this->response()->action_response_data = [
			'success' => true,
			'message' => 'License Check Scheduled',
		];
	}
}