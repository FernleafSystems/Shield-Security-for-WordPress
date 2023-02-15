<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockVisitor extends BaseAutoUnblockShield {

	public function canRunAutoUnblockProcess() :bool {
		return parent::canRunAutoUnblockProcess() && Services::Request()->isPost();
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

	public function isUnblockAvailable() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledAutoVisitorRecover() && parent::isUnblockAvailable();
	}
}