<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;

class ModCon extends BaseShield\ModCon {

	public function getDbHandler_Events() :Shield\Databases\Events\Handler {
		return $this->getDbH( 'events' );
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return ( $this->getDbHandler_Events() instanceof Shield\Databases\Events\Handler )
			   && $this->getDbHandler_Events()->isReady()
			   && parent::isReadyToExecute();
	}
}