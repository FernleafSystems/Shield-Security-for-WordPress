<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionProcessor;
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
			add_filter( 'icwp_shield_2fa_skip', fn( $canSkip ) => $canSkip || ReproduceClientAuthByKey::Auth(), 20 );

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

			/**
			 * Execute custom actions via MainWP API.
			 *
			 * SECURITY: Action Overrides Handling
			 *
			 * MainWP server sends action_overrides (e.g., is_nonce_verify_required=false) in POST data
			 * to allow server-to-server communication without nonce verification. However, passing these
			 * overrides directly through user input creates a CSRF bypass vulnerability where attackers
			 * could send action_overrides[is_nonce_verify_required]=0 to skip CSRF protection.
			 *
			 * Solution: ActionProcessor::getAction() strips action_overrides from all input data. We
			 * extract them here first, then set them programmatically via setActionOverride() only
			 * after verifying MainWP authentication. This ensures security controls are never
			 * controllable via user input, while preserving legitimate MainWP server-to-server
			 * functionality.
			 *
			 * We instantiate ActionProcessor directly  because we need the action object to
			 * call setActionOverride() before processing.
			 *
			 * @see ActionProcessor::getAction() - strips action_overrides from all input
			 * @see BaseAction::setActionOverride() - programmatic override setter
			 * @see SiteCustomAction.php - MainWP server sends overrides
			 */
			add_filter( 'mainwp_child_extra_execution', function ( $information, $post ) {
				$con = self::con();

				if ( !empty( $post[ $con->prefix( 'mwp-action' ) ] ) ) {
					try {
						$params = $post[ $con->prefix( 'mwp-params' ) ] ?? [];
						$actionOverrides = $params[ 'action_overrides' ] ?? [];

						$actionSlug = $post[ $con->prefix( 'mwp-action' ) ];
						$action = ( new ActionProcessor() )->getAction( $actionSlug, $params );

						if ( !empty( $actionOverrides ) && ReproduceClientAuthByKey::Auth() ) {
							foreach ( $actionOverrides as $overrideKey => $overrideValue ) {
								$action->setActionOverride( $overrideKey, $overrideValue );
							}
						}

						$action->process();
						$response = $action->response()->action_response_data;
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