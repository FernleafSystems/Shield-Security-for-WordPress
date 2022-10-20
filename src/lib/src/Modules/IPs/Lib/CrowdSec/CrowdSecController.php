<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;

class CrowdSecController extends ExecOnceModConsumer {

	use PluginCronsConsumer;

	/**
	 * @var CrowdSecCfg
	 */
	public $cfg;

	protected function canRun() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledCrowdSecAutoBlock();
	}

	protected function run() {
		$this->setupCronHooks();

		new Signals\EventsToSignals( $this->getCon(), $this->getCon()->is_mode_live );

		add_action( $this->getCon()->prefix( 'adhoc_cron_crowdsec_signals' ), function () {
			// This cron is initiated from within SignalsBuilder
			( new Signals\PushSignalsToCS() )
				->setMod( $this->getMod() )
				->execute();
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
		( new Decisions\ImportDecisions() )
			->setMod( $this->getMod() )
			->execute();
	}
}