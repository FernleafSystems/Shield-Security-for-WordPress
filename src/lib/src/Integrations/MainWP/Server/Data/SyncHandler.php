<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server\Data;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use MainWP\Dashboard\MainWP_DB;

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
		$con = $this->getCon();
		error_log( var_export( $info, true ) );
		MainWP_DB::instance()->update_website_option(
			$website,
			$con->prefix( 'shield-sync' ),
			wp_json_encode( $info[ $con->prefix( 'shield-sync' ) ] ?? [] )
		);
	}
}
