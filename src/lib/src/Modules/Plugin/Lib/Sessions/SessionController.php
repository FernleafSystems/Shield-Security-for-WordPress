<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Consumer\WpLoginCapture;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\PasswordGenerator;

class SessionController {

	use ExecOnce;
	use PluginControllerConsumer;
	use WpLoginCapture;

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
		$session = self::con()->this_req->session ?? null;
		if ( !$session instanceof SessionVO ) {
			$session = new SessionVO();
		}

		if ( !$session->valid ) {

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
					try {
						$session = $this->buildSession( $userID, $parsed[ 'token' ] );
						$userMeta = self::con()->user_metas->for( $user );
						if ( !empty( $userMeta ) ) {
							$userMeta->record->ip_ref = ( new IPRecords() )
								->loadIP( $session->shield[ 'ip' ] )
								->id;
						}
						self::con()->this_req->session = $session;
					}
					catch ( \Exception $e ) {
					}
				}
			}
		}

		return $session;
	}

	/**
	 * @throws \Exception
	 */
	public function buildSession( int $userID, string $token ) :SessionVO {
		$req = Services::Request();
		$thisReq = self::con()->this_req;

		$manager = \WP_Session_Tokens::get_instance( $userID );
		$session = $manager->get( $token );
		if ( !\is_array( $session ) ) {
			throw new \Exception( 'No such session available' );
		}

		// Ensure the correct IP is stored
		$srvIP = Services::IP();
		$ip = $thisReq->ip;
		if ( !empty( $ip ) && ( empty( $session[ 'ip' ] ) || !$srvIP->IpIn( $ip, [ $session[ 'ip' ] ] ) ) ) {
			$session[ 'ip' ] = $ip;
		}

		$shieldMeta = $session[ 'shield' ] ?? [];
		$shieldMeta[ 'user_id' ] = $userID;
		$shieldMeta[ 'expires_at' ] = $session[ 'expiration' ];
		$shieldMeta[ 'idle_interval' ] = $req->ts() - ( $shieldMeta[ 'last_activity_at' ] ?? $req->ts() );
		$shieldMeta[ 'last_activity_at' ] = $req->ts();
		if ( empty( $shieldMeta[ 'host' ] ) ) {
			$shieldMeta[ 'host' ] = $thisReq->host ?? $req->getHost();
		}
		if ( empty( $shieldMeta[ 'unique' ] ) ) {
			$shieldMeta[ 'unique' ] = PasswordGenerator::Gen( 12, false, true, false );
		}
		if ( !isset( $shieldMeta[ 'useragent' ] ) ) {
			$shieldMeta[ 'useragent' ] = self::con()->this_req->useragent;
		}
		if ( !isset( $shieldMeta[ 'ip' ] ) ) {
			$shieldMeta[ 'ip' ] = self::con()->this_req->ip;
		}

		$shieldMeta[ 'session_duration' ] = $req->ts() - ( $shieldMeta[ 'session_started_at' ] ?? $req->ts() );
		if ( !isset( $shieldMeta[ 'session_started_at' ] ) ) {
			$shieldMeta[ 'session_started_at' ] = $session[ 'login' ] ?? $req->ts();
		}

		$shieldMeta[ 'token_duration' ] = $req->ts() - ( $shieldMeta[ 'token_started_at' ] ?? $shieldMeta[ 'session_started_at' ] );
		if ( !isset( $shieldMeta[ 'token_started_at' ] ) ) {
			$shieldMeta[ 'token_started_at' ] = $session[ 'login' ] ?? $req->ts();
		}

		$session[ 'shield' ] = $shieldMeta;
		$manager->update( $token, $session );

		$VO = ( new SessionVO() )->applyFromArray( $session );
		$VO->valid = true;
		$VO->token = $token;

		return $VO;
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

	public function updateSessionParameter( SessionVO $session, string $key, $value ) {
		if ( $session->valid && ( $session->shield[ 'user_id' ] ?? 0 ) > 0 ) {
			$shield = $session->shield;
			$shield[ $key ] = $value;
			$session->shield = $shield;

			\WP_Session_Tokens::get_instance( $session->shield[ 'user_id' ] )
							  ->update(
								  $session->token,
								  $session->getRawData()
							  );
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

		unset( self::con()->this_req->session );

		return true;
	}
}