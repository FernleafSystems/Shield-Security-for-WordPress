<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\ToggleSecAdminStatus;

class SecurityAdminAuthClear extends SecurityAdminBase {

	public const SLUG = 'sec_admin_auth_clear';

	protected function exec() {
		$con = $this->con();
		( new ToggleSecAdminStatus() )
			->setMod( $con->getModule_SecAdmin() )
			->turnOff();

		$response = $this->response();
		$response->action_response_data = [
			'success' => true,
		];
		$response->next_step = [
			'type' => 'redirect',
			'url'  => $this->con()->plugin_urls->adminHome(),
		];
	}
}