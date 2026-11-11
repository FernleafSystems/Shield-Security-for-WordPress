<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin\Ops\RemoveSecAdmin;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Secadmin;

class SecurityAdminRemove extends SecurityAdminBase {

	public const SLUG = 'secadmin_remove_confirm';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_ORDINARY => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		( new RemoveSecAdmin() )->remove();
		$this->response()
			 ->setPayloadSuccess( true )
			 ->setPayloadRedirectNextStep( self::con()->plugin_urls->zone( Secadmin::Slug() ) );
	}
}
