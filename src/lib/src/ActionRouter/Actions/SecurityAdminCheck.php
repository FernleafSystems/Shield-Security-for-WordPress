<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\ModCon;

class SecurityAdminCheck extends SecurityAdminBase {

	public const SLUG = 'sec_admin_check';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$secAdminCon = $mod->getSecurityAdminController();
		$this->response()->action_response_data = [
			'time_remaining' => $secAdminCon->getSecAdminTimeRemaining(),
			'success'        => $this->getCon()->this_req->is_security_admin
		];
	}
}