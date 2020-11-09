<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

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

			add_filter( 'mainwp_child_extra_execution', function ( $information, $post ) {
				$con = $this->getCon();
				if ( !empty( $post[ $con->prefix( 'mainwp-action' ) ] ) ) {
					$information[ $con->prefix( 'mainwp-action' ) ] =
						wp_json_encode( ( new ApiActionInit() )
							->setMod( $this->getMod() )
							->run( $post[ $con->prefix( 'mainwp-action' ) ] ) );
				}
				return $information;
			}, 10, 2 );
		}
	}
}