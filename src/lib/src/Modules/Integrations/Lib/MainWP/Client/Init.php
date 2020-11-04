<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Init {

	use ModConsumer;

	public function run() {
		if ( Controller::isMainWPChildVersionSupported() ) {
			add_filter( 'mainwp_site_sync_others_data', function ( $information, $othersData ) {
				$con = $this->getCon();
				if ( isset( $othersData[ $con->prefix( 'mainwp-sync' ) ] ) ) {
					$information[ $con->prefix( 'mainwp-sync' ) ] = wp_json_encode( ( new Sync() )
						->setMod( $this->getMod() )
						->run() );
				}
				return $information;
			}, 10, 2 );
		}
	}
}