<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session;

/**
 * Not convinced that this is the best approach. It's a bit of a hack.
 */
class DeleteSession extends SessionsBase {

	public function byShieldIDs( array $IDs ) {
		foreach ( $IDs as $ID ) {
			$this->byShieldID( $ID );
		}
	}

	public function byShieldID( string $uniqueID ) {
		$session = $this->searchForSessionID( $uniqueID );
		if ( !empty( $session ) ) {
			$metaSessions = get_user_meta( $session[ 'shield' ][ 'user_id' ], 'session_tokens', true );
			unset( $metaSessions[ $session[ 'token' ] ] );
			update_user_meta( $session[ 'shield' ][ 'user_id' ], 'session_tokens', $metaSessions );
		}
	}

	public function searchForSessionID( string $id ) :?array {
		$theSession = null;

		$theUserID = null;
		$page = 1;
		$processedUserIDs = [];
		do {
			$UIDs = $this->queryUserMetaForIDs( $page++ );
			foreach ( $UIDs as $UID ) {
				if ( !\in_array( $UID, $processedUserIDs ) ) {
					$processedUserIDs[] = $UID;
					$handler = \WP_Session_Tokens::get_instance( $UID );
					if ( $handler instanceof \WP_User_Meta_Session_Tokens ) {
						foreach ( $handler->get_all() as $session ) {
							if ( !empty( $session[ 'shield' ] ) && ( $session[ 'shield' ][ 'unique' ] ?? '' ) === $id ) {
								$theUserID = $UID;
								break 3;
							}
						}
					}
				}
			}
		} while ( !empty( $UIDs ) );

		if ( !empty( $theUserID ) ) {
			$metaSessions = get_user_meta( $theUserID, 'session_tokens', true );
			foreach ( \is_array( $metaSessions ) ? $metaSessions : [] as $token => $sesh ) {
				if ( !empty( $sesh[ 'shield' ] ) && ( $sesh[ 'shield' ][ 'unique' ] ?? '' ) === $id ) {
					$sesh[ 'token' ] = $token;
					$theSession = $sesh;
				}
			}
		}

		return $theSession;
	}
}