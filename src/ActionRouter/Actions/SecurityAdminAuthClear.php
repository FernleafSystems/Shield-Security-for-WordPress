<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\ToggleSecAdminStatus;

class SecurityAdminAuthClear extends SecurityAdminBase {

	public const SLUG = 'sec_admin_auth_clear';

	protected function exec() {
		( new ToggleSecAdminStatus() )->turnOff();

		$this->response()
			->setPayloadSuccess( true )
			->setPayloadRedirectNextStep( self::con()->plugin_urls->adminRefererOrHome() );
	}
}
