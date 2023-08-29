<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

use FernleafSystems\Wordpress\Services\Services;

trait PluginCronsConsumer {

	public function runDailyCron() {
	}

	public function runHourlyCron() {
	}

	protected function setupCronHooks() {
		if ( Services::WpGeneral()->isCron() ) {
			add_action( self::con()->prefix( 'daily_cron' ), [ $this, 'runDailyCron' ] );
			add_action( self::con()->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
		}
	}
}