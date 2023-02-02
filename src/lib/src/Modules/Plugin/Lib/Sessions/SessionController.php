<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Session;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class SessionController extends ExecOnceModConsumer {

	use WpLoginCapture;

	/**
	 * @var SessionVO
	 */
	private $currentWP;

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

				$user = $WPUsers->getCurrentWpUser();
				$userID = $user instanceof \WP_User ? $user->ID : $this->getCapturedUserID();

				if ( !empty( $userID ) ) {
					$manager = \WP_Session_Tokens::get_instance( $userID );
					$session = $manager->get( $parsed[ 'token' ] );
					if ( is_array( $session ) ) {

						// Ensure the correct IP is stored
						$srvIP = Services::IP();
						$ip = $this->getCon()->this_req->ip;
						if ( !empty( $ip ) && ( empty( $session[ 'ip' ] ) || !$srvIP->checkIp( $ip, $session[ 'ip' ] ) ) ) {
							$session[ 'ip' ] = $ip;
						}

						$shieldSessionMeta = $session[ 'shield' ] ?? [];
						$shieldSessionMeta[ 'user_id' ] = $userID;
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
							$userMeta = $this->getCon()->getUserMeta( $WPUsers->getUserById( $userID ) );
							if ( !empty( $userMeta ) ) {
								$userMeta->record->ip_ref = ( new IPRecords() )
									->setMod( $this->getCon()->getModule_Data() )
									->loadIP( $session[ 'ip' ] )
									->id;
							}
						}
						catch ( \Exception $e ) {
						}
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
		$current = $this->getCurrentWP();
		if ( $current->valid ) {

			$user = Services::WpUsers()->getCurrentWpUser();
			$userID = $user instanceof \WP_User ? $user->ID : ( $current->shield[ 'user_id' ] ?? 0 );
			if ( !empty( $userID ) ) {
				$shield = $current->shield;
				$shield[ $key ] = $value;
				$current->shield = $shield;
				\WP_Session_Tokens::get_instance( $userID )
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
	}

	public function terminateCurrentSession() :bool {
		$current = $this->getCurrentWP();

		if ( $current->valid ) {
			$user = Services::WpUsers()->getCurrentWpUser();
			$userID = $user instanceof \WP_User ? $user->ID : ( $current->shield[ 'user_id' ] ?? 0 );
			if ( !empty( $userID ) ) {
				\WP_Session_Tokens::get_instance( $userID )->destroy( $current->token );
				$this->getCon()->fireEvent( 'session_terminate_current', [
					'audit_params' => [
						'user_login' => $user->user_login,
						'session_id' => $current->token,
					]
				] );
			}
		}

		unset( $this->currentWP );

		return true;
	}

	public function getSessionID() :string {
		return '';
	}
}