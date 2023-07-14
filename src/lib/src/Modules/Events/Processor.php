<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class Processor extends BaseShield\Processor {

	/**
	 * @var Events\Lib\StatsWriter
	 */
	private $oStatsWriter;

	protected function run() {
		$this->loadStatsWriter()->setIfCommit( true );
	}

	public function loadStatsWriter() :Events\Lib\StatsWriter {
		if ( !isset( $this->oStatsWriter ) ) {
			/** @var ModCon $mod */
			$mod = $this->mod();
			$this->oStatsWriter = ( new Events\Lib\StatsWriter( $this->con() ) )
				->setDbHandler( $mod->getDbHandler_Events() );
		}
		return $this->oStatsWriter;
	}

	public function runDailyCron() {
		( new Events\Consolidate\ConsolidateAllEvents() )->run();
	}
}