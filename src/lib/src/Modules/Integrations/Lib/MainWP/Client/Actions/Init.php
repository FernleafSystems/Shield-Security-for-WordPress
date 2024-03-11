<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\{
	Client\Auth\ReproduceClientAuthByKey,
	Controller
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Init {

	use PluginControllerConsumer;

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
						->setIP( self::con()->this_req->ip )
						->toManualWhitelist( 'MainWP Server (automatically added)' );
				}
				catch ( \Exception $e ) {
				}
			}, 10, 0 );

			// Augment Sync data with Shield Sync Data
			add_filter( 'mainwp_site_sync_others_data', function ( $information, $othersData ) {
				$con = self::con();
				if ( isset( $othersData[ $con->prefix( 'mainwp-sync' ) ] ) ) {
					$information[ $con->prefix( 'mainwp-sync' ) ] = wp_json_encode( ( new Sync() )->run() );
				}
				return $information;
			}, 10, 2 );

			// Execute custom actions via MainWP API.
			add_filter( 'mainwp_child_extra_execution', function ( $information, $post ) {
				$con = self::con();

				if ( !empty( $post[ $con->prefix( 'mwp-action' ) ] ) ) {
					try {
						$response = self::con()
							->action_router
							->action(
								$post[ $con->prefix( 'mwp-action' ) ],
								$post[ $con->prefix( 'mwp-params' ) ] ?? []
							)
							->action_response_data;
					}
					catch ( ActionException $ae ) {
						$response = [
							'success' => false,
							'message' => sprintf( 'Client site action failed: %s', $ae->getMessage() ),
						];
					}
					$information[ $con->prefix( 'mwp-action-response' ) ] = wp_json_encode( $response );
				}
				return $information;
			}, 10, 2 );
		}
	}
}