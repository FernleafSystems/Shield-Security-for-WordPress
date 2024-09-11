<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Capi\Enroll;

class CrowdsecResetEnrollment extends BaseAction {

	public const SLUG = 'crowdsec_reset_enrollment';

	protected function exec() {
		( new Enroll() )->clearEnrollment();
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}