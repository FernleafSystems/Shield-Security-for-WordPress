<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	protected function isReadyToExecute() :bool {
		return false;
	}

	/**
	 * @deprecated 17.0
	 */
	public function getDbHandler_Reports() :Databases\Reports\Handler {
		return $this->getDbH( 'reports' );
	}

	/**
	 * @inheritDoc
	 * @deprecated 17.0
	 */
	public function getDbHandlers( $bInitAll = false ) {
		return [];
	}
}