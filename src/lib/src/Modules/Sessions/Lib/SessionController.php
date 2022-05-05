<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Sessions\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class SessionController extends ExecOnceModConsumer {

	use WpLoginCapture;

	/**
	 * @var SessionVO
	 */
	private $currentWP;

	/**
	 * @var Session\EntryVO
	 * @deprecated 15.0
	 */
	private $current;

	/**
	 * @var ?string
	 * @deprecated 15.0
	 */
	private $sessionID;

	protected function run() {
		if ( !Services::WpUsers()->isProfilePage() && !Services::IP()->isLoopback() ) { // only on logout
			add_action( 'clear_auth_cookie', function () {
				$this->getCurrentWP();
			}, 0 );
		}

		$this->setToCaptureApplicationLogin( true )
			 ->setAllowMultipleCapture( true )
			 ->setupLoginCaptureHooks();
	}

	protected function captureLogin( \WP_User $user ) {
		if ( !empty( $this->getLoggedInCookie() ) ) {
			$this->getCurrentWP();
			$this->getCon()->fireEvent( 'login_success' );
		}
	}

	public function getCurrentWP() :SessionVO {

		if ( !isset( $this->currentWP ) ) {
			$this->currentWP = new SessionVO();
		}

		$WPUsers = Services::WpUsers();
		if ( !$this->currentWP->valid ) {

			if ( !empty( $this->getLoggedInCookie() ) ) {
				$parsed = wp_parse_auth_cookie( $this->getLoggedInCookie() );
			}
			if ( empty( $parsed ) ) {
				foreach ( [ 'logged_in', 'secure_auth', 'auth' ] as $type ) {
					$parsed = wp_parse_auth_cookie( '', $type );
					if ( !empty( $parsed ) ) {
						break;
					}
				}
			}

			if ( is_array( $parsed ) && !empty( $parsed[ 'token' ] ) ) {
				$manager = \WP_Session_Tokens::get_instance( $WPUsers->getCurrentWpUser()->ID );

				$session = $manager->get( $parsed[ 'token' ] );

				if ( is_array( $session ) ) {

					// Ensure the correct IP is stored
					$srvIP = Services::IP();
					$ip = $srvIP->getRequestIp();
					if ( !empty( $ip ) && ( empty( $session[ 'ip' ] ) || !$srvIP->checkIp( $ip, $session[ 'ip' ] ) ) ) {
						$session[ 'ip' ] = $ip;
					}

					$shieldSessionMeta = $session[ 'shield' ] ?? [];

					$shieldSessionMeta[ 'last_activity_at' ] = Services::Request()->ts();
					if ( empty( $shieldSessionMeta[ 'unique' ] ) ) {
						$shieldSessionMeta[ 'unique' ] = uniqid();
					}

					$session[ 'shield' ] = $shieldSessionMeta;
					$manager->update( $parsed[ 'token' ], $session );

					// all that follows should not be stored
					$session[ 'token' ] = $parsed[ 'token' ];
					// This is a copy of \WP_Session_Tokens::hash_token(). They made it private, cuz that's helpful.
					$session[ 'hashed_token' ] = function_exists( 'hash' ) ? hash( 'sha256', $parsed[ 'token' ] ) : sha1( $parsed[ 'token' ] );
					$session[ 'valid' ] = true;
					$this->currentWP->applyFromArray( $session );

					// Update User Last Seen IP.
					try {
						$this->getCon()->getCurrentUserMeta()->record->ip_ref = ( new IPRecords() )
							->setMod( $this->getCon()->getModule_Data() )
							->loadIP( $session[ 'ip' ], true )
							->id;
					}
					catch ( \Exception $e ) {
					}
				}
			}
		}

		return $this->currentWP;
	}

	/**
	 * This is a hack to directly access and set the raw data for the user sessions by removing 1 of the array entries.
	 */
	public function removeSessionBasedOnUniqueID( int $userID, string $uniqueID ) {
		$manager = \WP_Session_Tokens::get_instance( $userID );
		if ( $manager instanceof \WP_User_Meta_Session_Tokens ) {
			$raw = get_user_meta( $userID, 'session_tokens', true );
			foreach ( $raw as $hash => $session ) {
				if ( is_array( $session ) && $uniqueID === ( $session[ 'shield' ][ 'unique' ] ?? '' ) ) {
					unset( $raw[ $hash ] );
					update_user_meta( $userID, 'session_tokens', $raw );
					break;
				}
			}
		}
	}

	public function updateSessionParameter( string $key, $value ) {
		$WPUsers = Services::WpUsers();
		$current = $this->getCurrentWP();
		if ( $current->valid ) {
			$shield = $current->shield;
			$shield[ $key ] = $value;
			$current->shield = $shield;
			\WP_Session_Tokens::get_instance( $WPUsers->getCurrentWpUserId() )
							  ->update(
								  $current->token,
								  array_diff_key( $current->getRawData(), array_flip( [
									  'token',
									  'hashed_token',
									  'valid'
								  ] ) )
							  );
		}
	}

	/**
	 * @deprecated 15.0
	 */
	public function hasSession() :bool {
		return (bool)$this->getCurrentWP()->valid;
	}

	public function terminateCurrentSession() :bool {
		$current = $this->getCurrentWP();

		if ( $current->valid ) {
			$user = Services::WpUsers()->getCurrentWpUser();
			\WP_Session_Tokens::get_instance( $user->ID )->destroy( $current->token );
			$this->getCon()->fireEvent( 'session_terminate_current', [
				'audit_params' => [
					'user_login' => $user->user_login,
					'session_id' => $current->token,
				]
			] );
		}

		unset( $this->currentWP );

		return true;
	}

	/**
	 * @deprecated 15.0
	 */
	public function hasSessionID() :bool {
		return !empty( $this->getSessionID() );
	}

	public function getSessionID() :string {
		return '';
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