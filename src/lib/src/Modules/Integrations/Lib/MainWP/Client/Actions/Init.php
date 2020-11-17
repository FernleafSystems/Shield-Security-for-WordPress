<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\{
	Client\Auth\ReproduceClientAuthByKey,
	Controller
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\AddIp;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Init {

	use ModConsumer;

	public function run() {
		if ( Controller::isMainWPChildVersionSupported() ) {

			// Skip 2FA login if we can verify MainWP Authentication
			add_filter( 'icwp_shield_2fa_skip', function ( $canSkip ) {
				return $canSkip || ReproduceClientAuthByKey::Auth();
			}, 20, 1 );

			// Whitelist the MainWP Server IP
			add_action( 'mainwp_child_site_stats', function () {
				try {
					( new AddIp() )
						->setMod( $this->getCon()->getModule_IPs() )
						->setIP( Services::IP()->getRequestIp() )
						->toManualWhitelist( 'MainWP Server (automatically added)' );
				}
				catch ( \Exception $e ) {
				}
			}, 10, 0 );

			// Augment Sync data with Shield Sync Data
			add_filter( 'mainwp_site_sync_others_data', function ( $information, $othersData ) {
				$con = $this->getCon();
				if ( isset( $othersData[ $con->prefix( 'mainwp-sync' ) ] ) ) {
					$information[ $con->prefix( 'mainwp-sync' ) ] = wp_json_encode( ( new Sync() )
						->setMod( $this->getMod() )
						->run() );
				}
				return $information;
			}, 10, 2 );

			// Execute custom actions via MainWP API.
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