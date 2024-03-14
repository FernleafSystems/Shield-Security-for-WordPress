<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;

class CrowdSecController {

	use ExecOnce;
	use ModConsumer;
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
		$opts = self::con()->opts;
		return \method_exists( $opts, 'optGet' ) ?
			$opts->optGet( 'crowdsec_cfg' ) : $this->opts()->getOpt( 'crowdsec_cfg' );
	}

	public function getApi() :CrowdSecApi {
		return $this->api ?? $this->api = new CrowdSecApi();
	}

	public function storeCfg( CrowdSecCfg $cfg ) {
		$opts = self::con()->opts;
		\method_exists( $opts, 'optSet' ) ?
			$opts->optSet( 'crowdsec_cfg', $cfg->getRawData() )
			: $this->opts()->setOpt( 'crowdsec_cfg', $cfg->getRawData() );
		self::con()->opts->store();
	}

	public function runHourlyCron() {
		( new Decisions\ImportDecisions() )->execute();
	}
}