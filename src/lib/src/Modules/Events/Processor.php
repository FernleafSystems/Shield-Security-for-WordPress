<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;

class Processor extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\BaseShield\Processor {

	protected function run() {
		( new Lib\StatsWriter() )->setIfCommit( true );
	}

	public function runDailyCron() {
		( new Consolidate\ConsolidateAllEvents() )->run();
	}
}