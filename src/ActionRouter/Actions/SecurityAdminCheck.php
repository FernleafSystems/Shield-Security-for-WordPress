<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;

class SecurityAdminCheck extends SecurityAdminBase {

	public const SLUG = 'sec_admin_check';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$this->response()->setPayload( [
			'time_remaining' => self::con()->comps->sec_admin->getSecAdminTimeRemaining(),
		] )->setPayloadSuccess( (bool)( self::con()->this_req->is_security_admin ) );
	}
}
