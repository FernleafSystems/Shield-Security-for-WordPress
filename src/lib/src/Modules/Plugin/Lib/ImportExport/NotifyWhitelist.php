<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;

class NotifyWhitelist extends ExecOnceModConsumer {

	public const MOD = ModCon::SLUG;

	protected function canRun() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return !empty( $opts->getImportExportWhitelist() );
	}

	protected function run() {
		$cronHook = $this->con()->prefix( 'importexport_notify' );

		add_action( 'shield/after_form_submit_options_save', function () use ( $cronHook ) {
			// auto-import notify: ONLY when the options are being updated with a MANUAL save.
			$this->scheduleNotifyCron( $cronHook );
		}, 10, 0 );

		add_action( 'shield/event', function ( string $event ) use ( $cronHook ) {
			if ( $event === 'ip_bypass_add' ) {
				$this->scheduleNotifyCron( $cronHook );
			}
		} );

		$q = new WhitelistNotifyQueue( 'whitelist_notify_urls', $this->con()->prefix() );
		add_action( $cronHook, function () use ( $q ) {
			/** @var Options $opts */
			$opts = $this->getOptions();
			foreach ( $opts->getImportExportWhitelist() as $url ) {
				$q->push_to_queue( $url );
			}
			$q->save()->dispatch();
		} );
	}

	private function scheduleNotifyCron( string $cronHook ) {
		if ( !wp_next_scheduled( $cronHook ) ) {
			wp_schedule_single_event( Services::Request()->ts() + 30, $cronHook );
		}
	}
}
