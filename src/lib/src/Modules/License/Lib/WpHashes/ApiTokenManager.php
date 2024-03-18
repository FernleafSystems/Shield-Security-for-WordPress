<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\WpHashes;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\WPHashes\SolicitToken;
use FernleafSystems\Wordpress\Services\Services;

class ApiTokenManager {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var bool
	 */
	private $canRequestOverride = false;

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
				$token = $this->loadToken();
				try {
					$token = \array_merge( $token,
						\array_intersect_key(
							( new SolicitToken() )->send(),
							\array_flip( [ 'token', 'expires_at', 'valid_license' ] )
						)
					);
				}
				catch ( \Exception $e ) {
				}
				$token[ 'attempt_at' ] = Services::Request()->ts();
				$token[ 'next_attempt_from' ] = Services::Request()->ts() + \HOUR_IN_SECONDS;
				$this->storeToken( $token );
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

	private function storeToken( array $token ) {
		self::con()->opts->optSet( 'wphashes_api_token', $token );
	}

	private function clearToken() {
		$this->storeToken( [] );
	}

	public function setCanRequestOverride( bool $canRequest ) :self {
		$this->canRequestOverride = $canRequest;
		return $this;
	}
}