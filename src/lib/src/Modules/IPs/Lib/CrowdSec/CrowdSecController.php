<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;

class CrowdSecController extends ExecOnceModConsumer {

	use ExecOnce;
	use ModConsumer;
	use PluginCronsConsumer;

	/**
	 * @var CrowdSecCfg
	 */
	public $cfg;

	protected function canRun() :bool {
		return $this->opts()->isEnabledCrowdSecAutoBlock();
	}

	protected function run() {
		$this->setupCronHooks();

		new Signals\EventsToSignals( $this->con(), $this->con()->is_mode_live );

		add_action( $this->con()->prefix( 'adhoc_cron_crowdsec_signals' ), function () {
			// This cron is initiated from within SignalsBuilder
			( new Signals\PushSignalsToCS() )->execute();
		} );
	}

	public function cfg() :CrowdSecCfg {
		return ( new CrowdSecCfg() )->applyFromArray( $this->getOptions()->getOpt( 'crowdsec_cfg' ) );
	}

	public function getApi() :CrowdSecApi {
		return ( new CrowdSecApi() )->setMod( $this->getMod() );
	}

	public function storeCfg( CrowdSecCfg $cfg ) {
		$this->getOptions()->setOpt( 'crowdsec_cfg', $cfg->getRawData() );
		$this->getMod()->saveModOptions();
	}

	public function runHourlyCron() {
		( new Decisions\ImportDecisions() )->execute();
	}
}