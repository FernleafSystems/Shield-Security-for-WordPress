<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	public const SLUG = 'events';

	public function getDbH_Events() :DB\Event\Ops\Handler {
		return self::con()->db_con ?
			self::con()->db_con->loadDbH( 'event' ) : $this->getDbHandler()->loadDbH( 'event' );
	}

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return $this->getDbH_Events()->isReady();
	}
}