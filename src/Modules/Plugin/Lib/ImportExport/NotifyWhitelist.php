<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\QueueScheduler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class NotifyWhitelist {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		$scheduler = new QueueScheduler();
		$scheduler->setup();
		$legacyCronHook = self::con()->prefix( SiteRepository::OLD_NOTIFY_CRON );

		add_action( 'shield/after_form_submit_options_save', function () use ( $scheduler ) {
			// auto-import notify: ONLY when the options are being updated with a MANUAL save.
			$this->queueActiveSitesForSync( $scheduler );
		}, 10, 0 );

		add_action( 'shield/event', function ( string $event ) use ( $scheduler ) {
			if ( $event === 'ip_bypass_add' ) {
				$this->queueActiveSitesForSync( $scheduler );
			}
		} );

		add_action( $legacyCronHook, function () use ( $scheduler ) {
			$this->queueActiveSitesForSync( $scheduler );
		} );
	}

	private function queueActiveSitesForSync( QueueScheduler $scheduler ) :void {
		try {
			$repo = new SiteRepository();
			$repo->ensureLegacyImported();
			if ( $repo->queueAllActive() > 0 ) {
				$scheduler->scheduleSoon();
			}
		}
		catch ( \Throwable $e ) {
		}
	}
}
