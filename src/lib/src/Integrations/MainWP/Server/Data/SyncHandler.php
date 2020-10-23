<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\Data;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use MainWP\Dashboard\MainWP_DB;

class SyncHandler {

	use PluginControllerConsumer;
	use OneTimeExecute;

	protected function run() {
		add_action( 'mainwp_sync_others_data', function ( $othersData, $website ) {
			$othersData[ $this->getCon()->prefix( 'mainwp-sync' ) ] = 'shield';
			return $othersData;
		}, 10, 2 );
		add_action( 'mainwp_site_synced', function ( $website, $info ) {
			$this->syncSite( $website, $info );
		}, 10, 2 );
	}

	/**
	 * @param object $website
	 * @param array  $info
	 */
	private function syncSite( $website, array $info ) {
		$con = $this->getCon();
		MainWP_DB::instance()->update_website_option(
			$website,
			$con->prefix( 'mainwp-sync' ),
			$info[ $con->prefix( 'mainwp-sync' ) ] ?? '[]'
		);
	}
}