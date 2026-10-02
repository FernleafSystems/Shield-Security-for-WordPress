<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CentralController;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\ConnectionStore;
use FernleafSystems\Wordpress\Plugin\Shield\Rest\ShieldCentral\Support\PairingTokenStore;

class CentralFixtureBuilder {

	public const ENABLED_OPTION = 'shield_browser_fixture_central_enabled';

	public function run( string $action ) :array {
		switch ( $action ) {
			case 'reset':
			case 'cleanup':
				$this->clearState();
				if ( $action === 'reset' ) {
					\update_option( self::ENABLED_OPTION, true, false );
				}
				else {
					\delete_option( self::ENABLED_OPTION );
				}
				return $this->inspect();
			case 'issue-token':
				return ( new CentralController() )->issuePairingToken();
			case 'revoke-connection':
				\delete_option( ConnectionStore::OPTION_KEY );
				return $this->inspect();
			case 'inspect':
				return $this->inspect();
			default:
				throw new \InvalidArgumentException( 'Unknown Central fixture action.' );
		}
	}

	private function inspect() :array {
		$connection = ( new ConnectionStore() )->read();
		return [
			'connected' => $connection !== [],
			'site_uuid' => $connection[ 'site_uuid' ] ?? null,
		];
	}

	private function clearState() :void {
		global $wpdb;
		\delete_option( ConnectionStore::OPTION_KEY );
		\delete_option( PairingTokenStore::OPTION_KEY );
		// Browser lanes are single-site installations without an external object cache.
		$prefix = '_site_transient_shield_central_pairing_session_';
		$names = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $prefix ).'%'
		) );
		foreach ( $names as $name ) {
			\delete_site_transient( \substr( $name, \strlen( '_site_transient_' ) ) );
		}
	}
}
