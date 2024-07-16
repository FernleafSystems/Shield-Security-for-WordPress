<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class CrowdSecController {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	/**
	 * @var CrowdSecCfg
	 */
	public $cfg;

	private $api;

	protected function canRun() :bool {
		return self::con()->comps->opts_lookup->enabledCrowdSecAutoBlock();
	}

	protected function run() {
		$this->setupCronHooks();

		( new Signals\EventsToSignals() )->setIfCommit( self::con()->is_mode_live );

		add_action( self::con()->prefix( 'adhoc_cron_crowdsec_signals' ), function () {
			// This cron is initiated from within SignalsBuilder
			( new Signals\PushSignalsToCS() )->execute();
		} );
	}

	public function cfg() :CrowdSecCfg {
		return ( new CrowdSecCfg() )->applyFromArray( self::con()->opts->optGet( 'crowdsec_cfg' ) );
	}

	public function getApi() :CrowdSecApi {
		return $this->api ?? $this->api = new CrowdSecApi();
	}

	public function storeCfg( CrowdSecCfg $cfg ) {
		self::con()->opts->optSet( 'crowdsec_cfg', $cfg->getRawData() )->store();
	}

	public function runHourlyCron() {
		( new Decisions\ImportDecisions() )->execute();
	}
}