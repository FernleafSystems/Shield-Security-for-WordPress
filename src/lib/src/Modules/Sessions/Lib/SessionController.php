<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class SessionController {

	use ModConsumer;

	/**
	 * @var array
	 */
	private $currentWP;

	/**
	 * @var Session\EntryVO
	 */
	private $current;

	/**
	 * @var ?string
	 */
	private $sessionID;

	public function getCurrentWP() :array {
		$WPUsers = Services::WpUsers();

		if ( !isset( $this->currentWP ) && did_action( 'init' ) && $WPUsers->isUserLoggedIn() ) {
			$user = $WPUsers->getCurrentWpUser();

			foreach ( [ 'logged_in', 'secure_auth', 'auth' ] as $type ) {
				$parsed = wp_parse_auth_cookie( '', $type );
				if ( !empty( $parsed ) ) {
					break;
				}
			}

			if ( is_array( $parsed ) && !empty( $parsed[ 'token' ] ) ) {
				$maybe = \WP_Session_Tokens::get_instance( $user->ID )->get( $parsed[ 'token' ] );

				if ( is_array( $maybe ) ) {
					// This is a copy of \WP_Session_Tokens::hash_token(). They made it private, cuz that's helpful.
					$maybe[ 'token' ] = $parsed[ 'token' ];
					$maybe[ 'hashed_token' ] = function_exists( 'hash' ) ? hash( 'sha256', $parsed[ 'token' ] ) : sha1( $parsed[ 'token' ] );
					$this->currentWP = $maybe;
				}
			}
		}

		return $this->currentWP ?? [];
	}

	public function updateLastActivityAt() {
		$this->updateSessionParameter( 'last_activity_at', Services::Request()->ts() );
	}

	public function updateSessionParameter( string $key, $value ) {
		$WPUsers = Services::WpUsers();
		$current = $this->getCurrentWP();
		if ( !empty( $current ) ) {
			$current[ $key ] = $value;
			\WP_Session_Tokens::get_instance( $WPUsers->getCurrentWpUserId() )
							  ->update(
								  $current[ 'token' ],
								  array_diff_key( $current, array_flip( [ 'token', 'hashed_token' ] ) )
							  );
			unset( $this->currentWP );
		}
	}

	public function hasSession() :bool {
		return !empty( $this->getCurrentWP() );
	}

	public function terminateCurrentSession() :bool {
		$current = $this->getCurrentWP();

		if ( !empty( $current ) ) {
			$user = Services::WpUsers()->getCurrentWpUser();
			\WP_Session_Tokens::get_instance( $user->ID )->destroy( $current[ 'token' ] );
			$this->getCon()->fireEvent( 'session_terminate_current', [
				'audit_params' => [
					'user_login' => $user->user_login,
					'session_id' => $current[ 'token' ],
				]
			] );
		}

		unset( $this->currentWP );

		return true;
	}

	public function hasSessionID() :bool {
		return !empty( $this->getSessionID() );
	}

	public function getSessionID() :string {
		$current = $this->getCurrentWP();
		return empty( $current ) ? '' : $current[ 'token' ];
	}

	/**
	 * @param string $sessionID
	 * @return Session\EntryVO|null
	 * @deprecated 15.0
	 */
	private function queryGetSession( string $sessionID, $username = '' ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Session\Select $sel */
		$sel = $mod->getDbHandler_Sessions()->getQuerySelector();
		return $sel->retrieveUserSession( $sessionID, (string)$username );
	}

	/**
	 * @deprecated 15.0
	 */
	public function createSession( \WP_User $user, string $sessionID = '' ) :bool {
		return false;
	}

	/**
	 * @return Session\EntryVO|null
	 * @deprecated 15.0
	 */
	public function getCurrent() {
		return $this->current ?? null;
	}
}