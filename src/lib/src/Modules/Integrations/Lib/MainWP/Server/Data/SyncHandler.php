<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Data;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\ModConsumer;
use MainWP\Dashboard\MainWP_DB;

class SyncHandler {

	use ExecOnce;
	use ModConsumer;

	protected function run() {
		add_action( 'mainwp_sync_others_data', function ( $othersData, $website ) {
			$othersData[ $this->con()->prefix( 'mainwp-sync' ) ] = 'shield';
			return $othersData;
		}, 10, 2 );
		add_action( 'mainwp_site_synced', function ( $website, $info ) {
			$this->syncSite( $website, $info );
		}, 10, 2 );
	}

	/**
	 * @param object $website
	 */
	private function syncSite( $website, array $info ) {
		MainWP_DB::instance()->update_website_option(
			$website,
			$this->con()->prefix( 'mainwp-sync' ),
			$info[ $this->con()->prefix( 'mainwp-sync' ) ] ?? '[]'
		);
	}
}