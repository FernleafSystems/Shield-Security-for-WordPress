<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use AptowebDeps\CrowdSec\CapiClient\Watcher;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Exceptions\LibraryPrefixedAutoloadNotFoundException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Capi\Enroll;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class CrowdSecController {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	private ?Capi\Storage $cApiStore;

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

	/**
	 * @throws LibraryPrefixedAutoloadNotFoundException
	 */
	public function getCApiStore() :Capi\Storage {
		self::con()->includePrefixedVendor(); // TODO: confirm this method isn't called every load.
		return $this->cApiStore ??= new Capi\Storage();
	}

	/**
	 * @throws LibraryPrefixedAutoloadNotFoundException
	 */
	public function getCApiWatcher() :Watcher {
		self::con()->includePrefixedVendor(); // TODO: confirm this method isn't called every load.
		return new Watcher(
			[
				'env'               => self::con()->is_mode_live ? 'prod' : 'dev',
				'machine_id_prefix' => \substr( \str_replace( '-', '', ( new InstallationID() )->id() ), 0, 16 ),
				'scenarios'         => $this->getCApiStore()->retrieveScenarios(),
			],
			$this->getCApiStore(),
			new Capi\RequestHandler()
		);
	}

	public function storeCfg( CrowdSecCfg $cfg ) {
		self::con()->opts->optSet( 'crowdsec_cfg', $cfg->getRawData() )->store();
	}

	public function runHourlyCron() {
		try {
			( new Enroll() )->enroll();
		}
		catch ( \Exception $e ) {
		}
		( new Decisions\ImportDecisions() )->execute();
	}

	/**
	 * @deprecated 20.1
	 */
	public function getApi() :CrowdSecApi {
		return new CrowdSecApi();
	}
}