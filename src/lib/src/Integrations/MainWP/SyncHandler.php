<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class SyncHandler {

	use PluginControllerConsumer;
	use OneTimeExecute;

	protected function run() {
		add_action( 'mainwp_site_synced', function ( $website, $info ) {
			$this->syncSite( $website, $info );
		}, 10, 2 );
	}

	/**
	 * @param object $website
	 * @param array  $info
	 */
	private function syncSite( $website, array $info ) {
	}
}
