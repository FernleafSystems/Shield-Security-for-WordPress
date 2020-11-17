<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Crons;

trait PluginCronsConsumer {

	public function runDailyCron() {
	}

	public function runHourlyCron() {
	}

	protected function setupCronHooks() {
		add_action( $this->getCon()->prefix( 'daily_cron' ), [ $this, 'runDailyCron' ] );
		add_action( $this->getCon()->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
	}
}