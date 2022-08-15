<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	Lib\AutoUnblock\AutoUnblockCrowdsec,
	Lib\IpRules\IpRuleStatus,
	Options
};

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
		/** @var Options $opts */
		$opts = $this->getOptions();
		$this->cfg = ( new CrowdSecCfg() )->applyFromArray( $opts->getOpt( 'crowdsec_cfg' ) );
		$this->setupCronHooks();

		( new AutoUnblockCrowdsec() )
			->setMod( $this->getMod() )
			->execute();

		new Signals\EventsToSignals( $this->getCon(), true );

		add_action( $this->getCon()->prefix( 'adhoc_cron_crowdsec_signals' ), function () {
			// This cron is initiated from within SignalsBuilder
			( new Signals\PushSignalsToCS() )
				->setMod( $this->getMod() )
				->execute();
		} );
	}

	public function getApi() :CrowdSecApi {
		return ( new CrowdSecApi() )->setMod( $this->getMod() );
	}

	public function isIpBlockedOnCrowdSec( string $ip ) :bool {
		return ( new IpRuleStatus( $ip ) )->isBlockedByCrowdsec();
	}

	public function storeCfg() {
		$this->getOptions()->setOpt( 'crowdsec_cfg', $this->cfg->getRawData() );
		$this->getMod()->saveModOptions();
	}

	public function runHourlyCron() {
		( new Decisions\DownloadDecisionsUpdate() )
			->setMod( $this->getMod() )
			->execute();
	}
}