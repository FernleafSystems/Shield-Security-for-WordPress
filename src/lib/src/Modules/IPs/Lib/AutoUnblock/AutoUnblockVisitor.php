<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockVisitor extends BaseAutoUnblockShield {

	public function canRunAutoUnblockProcess() :bool {
		return parent::canRunAutoUnblockProcess() && Services::Request()->isPost();
	}

	public function isUnblockAvailable() :bool {
		return \in_array( 'gasp', self::con()->opts->optGet( 'user_auto_recover' ) ) && parent::isUnblockAvailable();
	}

	protected function preUnblockChecks() :bool {
		parent::preUnblockChecks();

		$req = Services::Request();
		if ( $req->post( '_confirm' ) !== 'Y' ) {
			throw new \Exception( 'No confirmation checkbox.' );
		}
		if ( !empty( $req->post( 'email' ) ) || !empty( $req->post( 'name' ) ) ) {
			throw new \Exception( 'Oh so yummy.' );
		}

		return true;
	}

	protected function getUnblockMethodName() :string {
		return 'Visitor Auto-Unblock';
	}
}