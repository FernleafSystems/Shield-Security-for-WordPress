<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Client;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Init {

	use PluginControllerConsumer;

	public function run() {
		add_filter( 'mainwp_site_sync_others_data', function ( $information, $othersData ) {
			$con = $this->getCon();
			if ( isset( $othersData[ $con->prefix( 'mainwp-sync' ) ] ) ) {
				$information[ $con->prefix( 'mainwp-sync' ) ] = wp_json_encode( ( new Sync() )
					->setCon( $con )
					->run() );
			}
			return $information;
		}, 10, 2 );
	}
}