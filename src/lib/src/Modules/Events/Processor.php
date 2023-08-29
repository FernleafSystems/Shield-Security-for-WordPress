<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\Processor {

	/**
	 * @var Lib\StatsWriter
	 */
	private $oStatsWriter;

	protected function run() {
		( new Lib\StatsWriter() )->setIfCommit( true );
	}

	/**
	 * @deprecated 18.2.9
	 */
	public function loadStatsWriter() :Lib\StatsWriter {
		return $this->oStatsWriter ?? $this->oStatsWriter = new Lib\StatsWriter();
	}

	public function runDailyCron() {
		( new Consolidate\ConsolidateAllEvents() )->run();
	}
}