<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events\Handler as LegacyEventsDB;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	public const SLUG = 'events';

	public function getDbHandler_Events() :LegacyEventsDB {
		return $this->getDbH( 'events' );
	}

	public function getDbH_Events() :DB\Event\Ops\Handler {
		return $this->getDbHandler()->loadDbH( 'event' );
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return $this->getDbH_Events()->isReady();
	}
}