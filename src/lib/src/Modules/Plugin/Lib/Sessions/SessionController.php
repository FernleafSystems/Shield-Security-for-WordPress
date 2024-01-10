<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;

class SessionController {

	use ExecOnce;
	use ModConsumer;
	use WpLoginCapture;

	/**
	 * @var SessionVO
	 */
	private $current;

	protected function run() {
		if ( !Services::WpUsers()->isProfilePage() && !Services::IP()->isLoopback() ) { // only on logout
			add_action( 'clear_auth_cookie', function () {
				$this->current();
			}, 0 );
		}

		$this->setToCaptureApplicationLogin()
			 ->setAllowMultipleCapture()
			 ->setupLoginCaptureHooks();
	}

	protected function captureLogin( \WP_User $user ) {
		if ( !empty( $this->getLoggedInCookie() ) ) {
			$this->current();
			self::con()->fireEvent( 'login_success' );
		}
	}

	public function current() :SessionVO {
		if ( !isset( $this->current ) ) {
			$this->current = new SessionVO();
		}

		if ( !$this->current->valid ) {
			$req = Services::Request();

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

			if ( \is_array( $parsed ) && !empty( $parsed[ 'token' ] ) ) {

				$WPUsers = Services::WpUsers();
				$user = $WPUsers->getCurrentWpUser();
				if ( !$user instanceof \WP_User ) {
					$user = $WPUsers->getUserById( $this->getCapturedUserID() );
				}
				$userID = $user instanceof \WP_User ? $user->ID : null;

				if ( !empty( $userID ) ) {
					$manager = \WP_Session_Tokens::get_instance( $userID );
					$session = $manager->get( $parsed[ 'token' ] );
					if ( \is_array( $session ) ) {

						// Ensure the correct IP is stored
						$srvIP = Services::IP();
						$ip = self::con()->this_req->ip;
						if ( !empty( $ip ) && ( empty( $session[ 'ip' ] ) || !$srvIP->IpIn( $ip, [ $session[ 'ip' ] ] ) ) ) {
							$session[ 'ip' ] = $ip;
						}

						$shieldMeta = $session[ 'shield' ] ?? [];
						$shieldMeta[ 'user_id' ] = $userID;
						$shieldMeta[ 'idle_interval' ] = $req->ts() - ( $shieldMeta[ 'last_activity_at' ] ?? $req->ts() );
						$shieldMeta[ 'last_activity_at' ] = $req->ts();
						if ( empty( $shieldMeta[ 'unique' ] ) ) {
							$shieldMeta[ 'unique' ] = uniqid();
						}
						if ( !isset( $shieldMeta[ 'useragent' ] ) ) {
							$shieldMeta[ 'useragent' ] = self::con()->this_req->useragent;
						}
						if ( !isset( $shieldMeta[ 'ip' ] ) ) {
							$shieldMeta[ 'ip' ] = self::con()->this_req->ip;
						}
						if ( !isset( $shieldMeta[ 'session_started_at' ] ) ) {
							$shieldMeta[ 'session_started_at' ] = $session[ 'login' ] ?? $req->ts();
						}
						if ( !isset( $shieldMeta[ 'token_started_at' ] ) ) {
							$shieldMeta[ 'token_started_at' ] = $session[ 'login' ] ?? $req->ts();
						}

						$session[ 'shield' ] = $shieldMeta;
						$manager->update( $parsed[ 'token' ], $session );

						// all that follows should not be stored
						$session[ 'token' ] = $parsed[ 'token' ];
						// This is a copy of \WP_Session_Tokens::hash_token(). They made it private, cuz that's helpful.
						$session[ 'hashed_token' ] = \function_exists( 'hash' ) ? \hash( 'sha256', $parsed[ 'token' ] ) : \sha1( $parsed[ 'token' ] );
						$session[ 'valid' ] = true;

						$this->current->applyFromArray( $session );

						// Update User Last Seen IP.
						try {
							$userMeta = self::con()->user_metas->for( $user );
							if ( !empty( $userMeta ) ) {
								$userMeta->record->ip_ref = ( new IPRecords() )
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

		return $this->current;
	}

	/**
	 * This is a hack to directly access and set the raw data for the user sessions by removing 1 of the array entries.
	 */
	public function removeSessionBasedOnUniqueID( int $userID, string $uniqueID ) {
		$manager = \WP_Session_Tokens::get_instance( $userID );
		if ( $manager instanceof \WP_User_Meta_Session_Tokens ) {
			$raw = get_user_meta( $userID, 'session_tokens', true );
			foreach ( $raw as $hash => $session ) {
				if ( \is_array( $session ) && $uniqueID === ( $session[ 'shield' ][ 'unique' ] ?? '' ) ) {
					unset( $raw[ $hash ] );
					update_user_meta( $userID, 'session_tokens', $raw );
					break;
				}
			}
		}
	}

	public function updateSessionParameter( string $key, $value ) {
		$current = $this->current();
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
									  \array_diff_key( $current->getRawData(), \array_flip( [
										  'token',
										  'hashed_token',
										  'valid'
									  ] ) )
								  );
			}
		}
	}

	public function terminateCurrentSession() :bool {
		$current = $this->current();
		if ( $current->valid ) {
			$user = Services::WpUsers()->getCurrentWpUser();
			$userID = $user instanceof \WP_User ? $user->ID : ( $current->shield[ 'user_id' ] ?? 0 );
			if ( !empty( $userID ) ) {
				\WP_Session_Tokens::get_instance( $userID )->destroy( $current->token );
				self::con()->fireEvent( 'session_terminate_current', [
					'audit_params' => [
						'user_login' => $user->user_login,
						'session_id' => $current->token,
					]
				] );
			}
		}

		unset( $this->current );

		return true;
	}

	public function getSessionID() :string {
		return '';
	}
}