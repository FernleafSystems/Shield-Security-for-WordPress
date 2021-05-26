<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use MainWP\Dashboard\MainWP_DB;

class SyncHandler extends ExecOnceModConsumer {

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