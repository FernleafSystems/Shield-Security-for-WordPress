<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Capi\Enroll;

class CrowdsecResetEnrollment extends BaseAction {

	public const SLUG = 'crowdsec_reset_enrollment';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		( new Enroll() )->clearEnrollment();
		$this->response()->setPayload()->setPayloadSuccess( true );
	}
}
