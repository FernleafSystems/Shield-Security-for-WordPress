<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ActionsMap;
use FernleafSystems\Wordpress\Services\Services;

class ActionNonce {

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction|string $action
	 * @return string
	 */
	public static function Create( string $action ) :string {
		return self::CreateNonces( $action )[ 0 ];
	}

	/**
	 * @param \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction|string $action
	 */
	public static function CreateNonces( string $action ) :array {
		return \array_map(
			function ( int $distance ) use ( $action ) {
				$action = ActionsMap::ActionFromSlug( $action );

				$nonceCfg = \array_merge( [
					'ip'  => true,
					'ttl' => 12,
				], $action::NonceCfg() );

				return \substr( wp_hash( \implode( '|', [
					sprintf( '%s-%s', ActionData::FIELD_SHIELD, $action::SLUG ),
					Services::WpUsers()->getCurrentWpUserId(),
					$nonceCfg[ 'ip' ] ? Services::Request()->ip() : '-',
					\ceil( Services::Request()->ts()/( \HOUR_IN_SECONDS*$nonceCfg[ 'ttl' ] ) ) - $distance,
				] ), 'nonce' ), -12, 10 );
			},
			[ 0, 1 ]
		);
	}

	public static function Verify( string $action, string $nonce ) :bool {
		$valid = false;
		foreach ( self::CreateNonces( $action ) as $expected ) {
			if ( \hash_equals( $expected, $nonce ) ) {
				$valid = true;
				break;
			}
		}
		return $valid;
	}

	public static function VerifyFromRequest() :bool {
		$req = Services::Request();
		return self::Verify( $req->request( ActionData::FIELD_EXECUTE ), $req->request( ActionData::FIELD_NONCE ) );
	}
}
