<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\Processor {

	/**
	 * @var Lib\StatsWriter
	 */
	private $oStatsWriter;

	protected function run() {
		$this->loadStatsWriter()->setIfCommit( true );
	}

	public function loadStatsWriter() :Lib\StatsWriter {
		if ( !isset( $this->oStatsWriter ) ) {
			$this->oStatsWriter = ( new Lib\StatsWriter( $this->con() ) )
				->setDbHandler( self::con()->getModule_Events()->getDbHandler_Events() );
		}
		return $this->oStatsWriter;
	}

	public function runDailyCron() {
		( new Consolidate\ConsolidateAllEvents() )->run();
	}
}