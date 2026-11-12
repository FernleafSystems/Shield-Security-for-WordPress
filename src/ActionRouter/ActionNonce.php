<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Services\Services;

/**
 * An action nonce is bound to the action and the current user, and stays valid for 12 to 24 hours.
 *
 * For a logged-in user it is also bound to their login session, as WordPress's own nonces are, so it stops
 * working when that session ends. A nonce that has to work in another session of the same user, such as one in
 * a link sent by email, is issued without that binding: its action declares
 * ExternalEntryGuard::PROOF_NONCE_ANY_SESSION.
 *
 * The session is the one the request arrived with. When a session is replaced during a request, nonces issued
 * in that request belong to the session that was replaced.
 */
class ActionNonce {

	private const TICK_HOURS = 12;

	/**
	 * The marker used in place of a session: for a logged-out visitor, who has none, and for a nonce that isn't
	 * bound to one. Formerly an optional IP binding; kept so these nonces stay valid across updates.
	 */
	private const NO_SESSION = '-';

	/**
	 * @param class-string<Actions\BaseAction> $action
	 */
	public static function Create( string $action, bool $sessionBound = true ) :string {
		return self::CreateNonces( $action, $sessionBound )[ 0 ];
	}

	public static function Verify( string $action, string $nonce, bool $sessionBound = true ) :bool {
		$valid = false;
		foreach ( self::CreateNonces( $action, $sessionBound ) as $expected ) {
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
	private static function CreateNonces( string $action, bool $sessionBound ) :array {
		$action = Utility\ActionsMap::ActionFromSlug( $action );
		$userID = (int)Services::WpUsers()->getCurrentWpUserId();
		$session = ( $sessionBound && $userID > 0 ) ? (string)\wp_get_session_token() : '';
		$current = (int)\ceil( Services::Request()->ts()/( \HOUR_IN_SECONDS*self::TICK_HOURS ) );
		return \array_map(
			fn( int $tick ) => \substr( wp_hash( \implode( '|', [
				sprintf( '%s-%s', ActionData::FIELD_SHIELD, $action::SLUG ),
				$userID,
				$session === '' ? self::NO_SESSION : $session,
				$tick,
			] ), 'nonce' ), -12, 10 ),
			[ $current, $current - 1 ]
		);
	}
}
