<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class SecurityAdminCheck extends SecurityAdminBase {

	public const SLUG = 'sec_admin_check';

	protected function exec() {
		$this->response()->action_response_data = [
			'time_remaining' => $this->getCon()
									 ->getModule_SecAdmin()
									 ->getSecurityAdminController()
									 ->getSecAdminTimeRemaining(),
			'success'        => $this->getCon()->this_req->is_security_admin
		];
	}
}