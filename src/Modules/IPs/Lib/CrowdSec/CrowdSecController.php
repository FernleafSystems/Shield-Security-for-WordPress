<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use CrowdSec\CapiClient\Watcher;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\ClassDependencyGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Dependencies\Exceptions\LibraryNotFoundException;
use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Capi\Enroll;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CrowdSecController {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	private Capi\Storage $cApiStore;

	protected function canRun() :bool {
		return self::con()->comps->opts_lookup->enabledCrowdSecAutoBlock();
	}

	protected function run() {
		$this->attachHooks();
		( new Signals\EventsToSignals() )->setIfCommit( self::con()->is_mode_live );
	}

	protected function attachHooks() :void {
		$this->setupCronHooks();
		add_action( $this->cronSignalsPushKey(), fn() => ( new Signals\PushSignalsToCS() )->execute() );
	}

	public function cfg() :CrowdSecCfg {
		return ( new CrowdSecCfg() )->applyFromArray( self::con()->opts->optGet( 'crowdsec_cfg' ) );
	}

	/**
	 * IMPORTANT: this should only be called sparingly and only as-required. Ideally on a CRON.
	 */
	public function getCApiStore() :Capi\Storage {
		return $this->cApiStore ??= new Capi\Storage();
	}

	/**
	 * IMPORTANT: this should only be called sparingly and only as-required. Ideally on a CRON.
	 *
	 * @throws LibraryNotFoundException
	 */
	public function getCApiWatcher() :Watcher {
		( new ClassDependencyGuard() )->ensureAvailable( '\CrowdSec\CapiClient\Watcher', 'CrowdSec' );

		$store = $this->getCApiStore();
		return new Watcher(
			[
				'env'               => self::con()->is_mode_live ? 'prod' : 'dev',
				'machine_id_prefix' => \substr( \str_replace( '-', '', ( new InstallationID() )->id() ), 0, 16 ),
				'scenarios'         => $store->retrieveScenarios(),
			],
			$store,
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

		if ( self::con()->db_con->crowdsec_signals->getQuerySelector()->count() > 0 ) {
			$this->scheduleSignalsPushCron();
		}
	}

	public function scheduleSignalsPushCron() :void {
		if ( !wp_next_scheduled( $this->cronSignalsPushKey() ) ) {
			wp_schedule_single_event(
				Services::Request()->ts()
				+ apply_filters( 'shield/crowdsec/signals_cron_interval', \MINUTE_IN_SECONDS*5 ),
				$this->cronSignalsPushKey()
			);
		}
	}

	private function cronSignalsPushKey() :string {
		return self::con()->prefix( 'adhoc_cron_crowdsec_signals' );
	}
}
