<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	public const SLUG = 'events';

	/**
	 * @throws \Exception
	 */
	protected function isReadyToExecute() :bool {
		return self::con()->db_con->dbhEvents()->isReady();
	}

	/**
	 * @deprecated 19.1
	 */
	public function getDbH_Events() :DB\Event\Ops\Handler {
		return self::con()->db_con->loadDbH( 'event' );
	}
}