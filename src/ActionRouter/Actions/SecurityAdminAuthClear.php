<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\ToggleSecAdminStatus;

class SecurityAdminAuthClear extends SecurityAdminBase {

	use Traits\NonceVerifyRequired;

	public const SLUG = 'sec_admin_auth_clear';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		( new ToggleSecAdminStatus() )->turnOff();

		$this->response()
			 ->setPayloadSuccess( true )
			 ->setPayloadRedirectNextStep( self::con()->plugin_urls->adminRefererOrHome() );
	}
}
