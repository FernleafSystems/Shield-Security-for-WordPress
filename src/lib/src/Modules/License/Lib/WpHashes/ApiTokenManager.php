<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\License\Lib\WpHashes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Token;

class ApiTokenManager {

	use ModConsumer;

	/**
	 * @var bool
	 */
	private $bCanRequestOverride = false;

	public function run() {
		add_action( $this->getCon()->prefix( 'event' ), function ( $sEventTag ) {
			switch ( $sEventTag ) {
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
	 * @return bool
	 */
	public function hasToken() {
		$sTok = $this->getTheToken();
		return strlen( $sTok ) == 40 && !$this->isExpired();
	}

	/**
	 * @return string
	 */
	public function getToken() {

		if ( $this->getCon()->getModule_License()->getLicenseHandler()->getLicense()->isValid() ) {
			$aT = $this->loadToken();
			if ( $this->isExpired() && $this->canRequestNewToken() ) {
				$aT = $this->loadToken();
				try {
					$aT = array_merge( $aT, $this->solicitApiToken() );
				}
				catch ( \Exception $oE ) {
				}
				$aT[ 'attempt_at' ] = Services::Request()->ts();
				$this->storeToken( $aT );
			}
		}
		else {
			$this->storeToken( [] );
		}

		return empty( $aT[ 'token' ] ) ? '' : $aT[ 'token' ];
	}

	/**
	 * @return string
	 */
	private function getTheToken() {
		return $this->loadToken()[ 'token' ];
	}

	/**
	 * @return array - return Token exactly as it's saved currently
	 */
	private function loadToken() {
		return array_merge(
			[
				'token'         => '',
				'expires_at'    => 0,
				'attempt_at'    => 0,
				'valid_license' => false,
			],
			$this->getOptions()->getOpt( 'wphashes_api_token', [] )
		);
	}

	/**
	 * @return bool
	 */
	private function canRequestNewToken() {
		return $this->getCanRequestOverride() ||
			   (
				   Services::Request()->carbon()->subHours( 6 )->timestamp > $this->loadToken()[ 'attempt_at' ]
				   && $this->getCon()->getModule_License()->getLicenseHandler()->getLicense()->isValid()
			   );
	}

	/**
	 * @return bool
	 */
	public function getCanRequestOverride() {
		return (bool)$this->bCanRequestOverride;
	}

	/**
	 * @return bool
	 */
	public function isExpired() {
		return Services::Request()->ts() > $this->loadToken()[ 'expires_at' ];
	}

	/**
	 * @param array $aToken
	 * @return $this
	 */
	private function storeToken( array $aToken = [] ) {
		$this->getOptions()->setOpt( 'wphashes_api_token', $aToken );
		return $this;
	}

	/**
	 * @param bool $bCanRequest
	 * @return $this
	 */
	public function setCanRequestOverride( $bCanRequest ) {
		$this->bCanRequestOverride = (bool)$bCanRequest;
		return $this;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	private function solicitApiToken() {
		$aResp = ( new Token\Solicit() )->retrieve(
			Services::WpGeneral()->getHomeUrl(),
			$this->getCon()->getSiteInstallationId()
		);
		if ( !is_array( $aResp ) || empty( $aResp[ 'token' ] ) || strlen( $aResp[ 'token' ] ) != 40 ) {
			throw new \Exception( 'Could not retrieve token' );
		}
		return $aResp;
	}
}