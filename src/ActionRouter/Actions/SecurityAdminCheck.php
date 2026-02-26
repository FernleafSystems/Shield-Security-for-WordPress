<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class SecurityAdminCheck extends SecurityAdminBase {

	public const SLUG = 'sec_admin_check';

	protected function exec() {
		$this->response()->setPayload( [
			'time_remaining' => self::con()->comps->sec_admin->getSecAdminTimeRemaining(),
		] )->setPayloadSuccess( (bool)( self::con()->this_req->is_security_admin ) );
	}
}
