<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events\Handler as LegacyEventsDB;

class ModCon extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\ModCon {

	public const SLUG = 'events';

	private $eventsMigrator;

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

	/**
	 * @deprecated 18.5
	 */
	public function onWpLoaded() {
		parent::onWpLoaded();
		$this->getMigrator();
	}

	/**
	 * @deprecated 18.5
	 */
	public function getMigrator() :Lib\QueueEventsDbMigrator {
		return $this->eventsMigrator ?? $this->eventsMigrator = new Lib\QueueEventsDbMigrator();
	}

	public function runDailyCron() {
		parent::runDailyCron();
		$this->getMigrator()->dispatch();
	}
}