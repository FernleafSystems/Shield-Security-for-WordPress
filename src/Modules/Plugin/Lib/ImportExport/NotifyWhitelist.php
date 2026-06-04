<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class NotifyWhitelist {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		$legacyCronHook = self::con()->prefix( SiteRepository::OLD_NOTIFY_CRON );

		add_action( 'shield/after_form_submit_options_save', function () {
			// auto-import notify: ONLY when the options are being updated with a MANUAL save.
			$this->queueActiveSitesForSync();
		}, 10, 0 );

		add_action( 'shield/event', function ( string $event ) {
			if ( $event === 'ip_bypass_add' ) {
				$this->queueActiveSitesForSync();
			}
		} );

		add_action( $legacyCronHook, function () {
			$this->queueActiveSitesForSync();
		} );
	}

	private function queueActiveSitesForSync() :void {
		$importExport = new ImportExportController();
		if ( !$importExport->isSyncEnabled() ) {
			return;
		}

		try {
			$repo = new SiteRepository();
			$importExport->ensureSitesRegistryImported();
			if ( $repo->queueAllActive() > 0 ) {
				$importExport->scheduleQueueSoonIfSyncEnabled();
			}
		}
		catch ( \Throwable $e ) {
		}
	}
}
