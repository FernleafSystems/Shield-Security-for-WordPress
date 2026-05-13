<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\WpHashes;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\WPHashes\SolicitToken;
use FernleafSystems\Wordpress\Services\Services;

class ApiTokenManager {

	use ExecOnce;
	use PluginControllerConsumer;

	private bool $canRequestOverride = false;

	protected function run() {
		add_action( 'shield/event', function ( $eventTag ) {
			switch ( $eventTag ) {
				case 'lic_check_success':
					$this->setCanRequestOverride( true )->getToken();
					break;
				case 'lic_fail_deactivate':
					$this->clearToken();
					break;
				default:
					break;
			}
		} );
	}

	/**
	 * IMPORTANT:
	 * To 'Pro Plugin' Nullers: Modifying this wont do your fake PRO registration any good.
	 * The WP Hashes Token API request will always fail for invalid PRO sites.
	 * Please don't change it, as the only result is invalid requests against our API.
	 * Eventually we will completely block their IP addresses and this will result in blocks for the
	 * API requests which don't even require an API Token
	 */
	public function getToken() :string {

		if ( self::con()->comps->license->getLicense()->isValid() ) {
			$token = $this->loadToken();
			if ( $this->isExpired() && $this->canRequestNewToken() ) {
				$now = Services::Request()->ts();
				$token[ 'attempt_at' ] = $now;
				$token[ 'next_attempt_from' ] = $now + \HOUR_IN_SECONDS;
				$this->storeToken( $token, true );

				try {
					$apiToken = \array_intersect_key(
						( new SolicitToken() )->send(),
						\array_flip( [ 'token', 'expires_at', 'valid_license' ] )
					);
					if ( !empty( $apiToken ) ) {
						$token = \array_merge( $token, $apiToken );
						$this->storeToken( $token, true );
					}
				}
				catch ( \Exception $e ) {
				}
			}
		}
		else {
			$this->clearToken();
		}

		return empty( $token[ 'token' ] ) ? '' : $token[ 'token' ];
	}

	public function hasToken() :bool {
		return \strlen( $this->getToken() ) === 40 && !$this->isExpired();
	}

	/**
	 * retrieve Token exactly as it's saved
	 *
	 * @return array{token:string, expires_at:int, attempt_at:int, next_attempt_from:int, valid_license:bool}
	 */
	private function loadToken() :array {
		return \array_merge( [
			'token'             => '',
			'expires_at'        => 0,
			'attempt_at'        => 0,
			'next_attempt_from' => 0,
			'valid_license'     => false,
		], self::con()->opts->optGet( 'wphashes_api_token' ) );
	}

	private function canRequestNewToken() :bool {
		return $this->getCanRequestOverride() ||
			   (
				   Services::Request()->ts() >= $this->getNextAttemptAllowedFrom()
				   && self::con()->comps->license->getLicense()->isValid()
			   );
	}

	public function getCanRequestOverride() :bool {
		return $this->canRequestOverride;
	}

	public function getExpiresAt() :int {
		return $this->loadToken()[ 'expires_at' ];
	}

	public function getNextAttemptAllowedFrom() :int {
		return $this->loadToken()[ 'next_attempt_from' ];
	}

	public function getPreviousAttemptAt() :int {
		return $this->loadToken()[ 'attempt_at' ];
	}

	public function isExpired() :bool {
		return Services::Request()->ts() > $this->getExpiresAt();
	}

	public function isNearlyExpired() :bool {
		return Services::Request()->carbon()->addHours( 2 )->timestamp > $this->getExpiresAt();
	}

	private function storeToken( array $token, bool $persist = false ) :void {
		self::con()->opts->optSet( 'wphashes_api_token', $token );
		if ( $persist ) {
			self::con()->opts->store();
		}
	}

	private function clearToken() :void {
		$this->storeToken( [] );
	}

	public function setCanRequestOverride( bool $canRequest ) :self {
		$this->canRequestOverride = $canRequest;
		return $this;
	}
}
