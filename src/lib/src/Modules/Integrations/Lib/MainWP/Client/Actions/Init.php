<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\{
	Client\Auth\ReproduceClientAuthByKey,
	Controller
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Init {

	use ModConsumer;

	public function run() {
		if ( Controller::isMainWPChildVersionSupported() ) {

			// Skip 2FA login if we can verify MainWP Authentication
			add_filter( 'icwp_shield_2fa_skip', function ( $canSkip ) {
				return $canSkip || ReproduceClientAuthByKey::Auth();
			}, 20 );

			// Whitelist the MainWP Server IP
			add_action( 'mainwp_child_site_stats', function () {
				try {
					( new AddRule() )
						->setMod( $this->getCon()->getModule_IPs() )
						->setIP( $this->getCon()->this_req->ip )
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

				if ( !empty( $post[ $con->prefix( 'mwp-action' ) ] ) ) {
					try {
						$response = $this->getCon()
							->action_router
							->action(
								$post[ $con->prefix( 'mwp-action' ) ],
								$post[ $con->prefix( 'mwp-params' ) ] ?? []
							)
							->action_response_data;
					}
					catch ( ActionException $e ) {
						$response = [
							'success' => false,
							'message' => 'Client action failed: '.$e->getMessage(),
						];
					}
					$information[ $con->prefix( 'mwp-action-response' ) ] = wp_json_encode( $response );
				}
				return $information;
			}, 10, 2 );
		}
	}
}