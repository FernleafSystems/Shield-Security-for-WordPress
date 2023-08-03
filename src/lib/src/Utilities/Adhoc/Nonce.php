<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\Adhoc;

use FernleafSystems\Wordpress\Services\Services;

class Nonce {

	public const LIFETIME = 12;

	public static function Create( string $action ) :string {
		return self::CreateNonces( $action )[ 0 ];
	}

	private static function CreateNonces( string $action ) :array {
		return \array_map( function ( int $distance ) use ( $action ) {
			return \substr( wp_hash( \implode( '|', [
				$action,
				Services::WpUsers()->getCurrentWpUserId(),
				Services::Request()->ip(),
				\ceil( Services::Request()->ts()/( \HOUR_IN_SECONDS*self::LIFETIME ) ) - $distance,
			] ), 'nonce' ), -12, 10 );
		}, [ 0, 1 ] );
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
}
