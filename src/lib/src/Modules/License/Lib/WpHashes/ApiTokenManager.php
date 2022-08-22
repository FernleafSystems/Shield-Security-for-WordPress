<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\WpHashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\WPHashes\SolicitToken;
use FernleafSystems\Wordpress\Services\Services;

class ApiTokenManager extends Modules\Base\Common\ExecOnceModConsumer {

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
					$this->storeToken( [] );
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
	 *
	 * @return string
	 */
	public function getToken() {

		if ( $this->getCon()->getModule_License()->getLicenseHandler()->getLicense()->isValid() ) {
			$token = $this->loadToken();
			if ( $this->isExpired() && $this->canRequestNewToken() ) {
				$token = $this->loadToken();
				try {
					$token = array_merge( $token,
						array_intersect_key(
							( new SolicitToken() )
								->setMod( $this->getCon()->getModule_Plugin() )
								->send(),
							array_flip( [ 'token', 'expires_at', 'valid_license' ] )
						)
					);
				}
				catch ( \Exception $e ) {
				}
				$token[ 'attempt_at' ] = Services::Request()->ts();
				$token[ 'next_attempt_from' ] = Services::Request()->ts() + HOUR_IN_SECONDS;
				$this->storeToken( $token );
			}
		}
		else {
			$this->storeToken( [] );
		}

		return empty( $token[ 'token' ] ) ? '' : $token[ 'token' ];
	}

	public function hasToken() :bool {
		return strlen( $this->getToken() ) == 40 && !$this->isExpired();
	}

	/**
	 * retrieve Token exactly as it's saved
	 */
	private function loadToken() :array {
		return array_merge(
			[
				'token'             => '',
				'expires_at'        => 0,
				'attempt_at'        => 0,
				'next_attempt_from' => 0,
				'valid_license'     => false,
			],
			$this->getOptions()->getOpt( 'wphashes_api_token', [] )
		);
	}

	private function canRequestNewToken() :bool {
		return $this->getCanRequestOverride() ||
			   (
				   Services::Request()->ts() >= $this->getNextAttemptAllowedFrom()
				   && $this->getCon()->getModule_License()->getLicenseHandler()->getLicense()->isValid()
			   );
	}

	public function getCanRequestOverride() :bool {
		return $this->canRequestOverride;
	}

	/**
	 * @return int
	 */
	public function getExpiresAt() {
		return $this->loadToken()[ 'expires_at' ];
	}

	/**
	 * @return int
	 */
	public function getNextAttemptAllowedFrom() {
		return $this->loadToken()[ 'next_attempt_from' ];
	}

	/**
	 * @return int
	 */
	public function getPreviousAttemptAt() {
		return $this->loadToken()[ 'attempt_at' ];
	}

	public function isExpired() :bool {
		return Services::Request()->ts() > $this->getExpiresAt();
	}

	public function isNearlyExpired() :bool {
		return Services::Request()->carbon()->addHours( 2 )->timestamp > $this->getExpiresAt();
	}

	/**
	 * @return $this
	 */
	private function storeToken( array $token = [] ) {
		$this->getOptions()->setOpt( 'wphashes_api_token', $token );
		return $this;
	}

	/**
	 * @return $this
	 */
	public function setCanRequestOverride( bool $canRequest ) {
		$this->canRequestOverride = $canRequest;
		return $this;
	}
}