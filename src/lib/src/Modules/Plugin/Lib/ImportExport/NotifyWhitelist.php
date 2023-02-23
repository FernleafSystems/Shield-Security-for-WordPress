<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;

class NotifyWhitelist extends ExecOnceModConsumer {

	public const MOD = ModCon::SLUG;

	protected function canRun() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return !empty( $opts->getImportExportWhitelist() );
	}

	protected function run() {
		$q = new WhitelistNotifyQueue( 'whitelist_notify_urls', $this->getCon()->prefix() );
		add_action( $this->getCon()->prefix( 'importexport_notify' ), function () use ( $q ) {
			/** @var Options $opts */
			$opts = $this->getOptions();
			foreach ( $opts->getImportExportWhitelist() as $url ) {
				$q->push_to_queue( $url );
			}
			$q->save()->dispatch();
		} );
	}
}
