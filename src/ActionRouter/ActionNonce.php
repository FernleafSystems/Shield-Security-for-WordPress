<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Services\Services;

/**
 * An action nonce is bound to the action and the current user, and stays valid for 12 to 24 hours.
 */
class ActionNonce {

	private const TICK_HOURS = 12;

	/**
	 * @param class-string<Actions\BaseAction> $action
	 */
	public static function Create( string $action ) :string {
		return self::CreateNonces( $action )[ 0 ];
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

	/**
	 * @return string[] the nonce for the current tick, then for the previous tick.
	 */
	private static function CreateNonces( string $action ) :array {
		$action = Utility\ActionsMap::ActionFromSlug( $action );
		$current = (int)\ceil( Services::Request()->ts()/( \HOUR_IN_SECONDS*self::TICK_HOURS ) );
		return \array_map(
			fn( int $tick ) => \substr( wp_hash( \implode( '|', [
				sprintf( '%s-%s', ActionData::FIELD_SHIELD, $action::SLUG ),
				Services::WpUsers()->getCurrentWpUserId(),
				// Formerly an optional IP binding; kept as a fixed marker so issued nonces stay valid.
				'-',
				$tick,
			] ), 'nonce' ), -12, 10 ),
			[ $current, $current - 1 ]
		);
	}
}
