<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class CrowdsecResetEnrollment extends BaseAction {

	public const SLUG = 'crowdsec_reset_enrollment';

	protected function exec() {
		self::con()->comps->crowdsec->getApi()->clearEnrollment();
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}