<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Crowdsec\RetrieveScenarios;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Services\Services;

class CrowdSecApi {

	use ModConsumer;

	const STATE_NO_URL = 'no_url';
	const STATE_INVALID_URL = 'invalid_url';
	const STATE_NO_MACHINE_ID = 'no_mach_id';
	const STATE_NO_PASSWORD = 'no_password';
	const STATE_MACHINE_NOT_REGISTERED = 'mach_not_registered';
	const STATE_NO_AUTH_TOKEN = 'no_auth_token';
	const STATE_NO_AUTH_EXPIRE = 'no_auth_expire';
	const STATE_AUTH_EXPIRED = 'auth_expired';
	const STATE_NO_ENROLL_ID = 'no_enroll_id';
	const STATE_MACH_NOT_ENROLLED = 'mach_not_enrolled';
	const STATE_READY = 'ready';

	public function isReady() :bool {
		$this->login();
		return in_array( $this->getAuthStatus(), [
			self::STATE_NO_ENROLL_ID,
			self::STATE_MACH_NOT_ENROLLED,
			self::STATE_READY
		] );
	}

	public function getAuthorizationToken() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $this->isReady() ?
			$mod->getCrowdSecCon()->cfg->cs_auths[ Services::WpGeneral()->getWpUrl() ][ 'auth_token' ] : '';
	}

	public function getMachineID() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $this->isReady() ?
			$mod->getCrowdSecCon()->cfg->cs_auths[ Services::WpGeneral()->getWpUrl() ][ 'machine_id' ] : '';
	}

	public function getAuthStatus() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$siteURL = Services::WpGeneral()->getWpUrl();
		$csAuth = $mod->getCrowdSecCon()->cfg->cs_auths[ $siteURL ] ?? [];

		if ( empty( $csAuth[ 'url' ] ) ) {
			$state = self::STATE_NO_URL;
		}
		elseif ( $csAuth[ 'url' ] !== $siteURL ) {
			$state = self::STATE_INVALID_URL;
		}
		elseif ( empty( $csAuth[ 'machine_id' ] ) ) {
			$state = self::STATE_NO_MACHINE_ID;
		}
		elseif ( empty( $csAuth[ 'password' ] ) ) {
			$state = self::STATE_NO_PASSWORD;
		}
		elseif ( empty( $csAuth[ 'machine_registered' ] ) ) {
			$state = self::STATE_MACHINE_NOT_REGISTERED;
		}
		elseif ( empty( $csAuth[ 'auth_token' ] ) ) {
			$state = self::STATE_NO_AUTH_TOKEN;
		}
		elseif ( empty( $csAuth[ 'auth_expire' ] ) ) {
			$state = self::STATE_NO_AUTH_EXPIRE;
		}
		elseif ( $csAuth[ 'auth_expire' ] - Services::Request()->ts() < 0 ) {
			$state = self::STATE_AUTH_EXPIRED;
		}
		elseif ( empty( $this->getOptions()->getOpt( 'cs_enroll_id' ) ) ) {
			$state = self::STATE_NO_ENROLL_ID;
		}
		elseif ( empty( $csAuth[ 'machine_enrolled' ] ) ) {
			$state = self::STATE_MACH_NOT_ENROLLED;
		}
		else {
			$state = self::STATE_READY;
		}

		return $state;
	}

	public function login() :bool {
		$machineID = $this->getCon()->getInstallationID()[ 'id' ];

		$csAuth = $this->getCsAuth();
		$siteURL = Services::WpGeneral()->getWpUrl();

		if ( ( $csAuth[ 'url' ] ?? '' ) !== $siteURL ) {
			$csAuth = [
				'url' => $siteURL,
			];
		}

		if ( empty( $csAuth[ 'machine_id' ] ) || $csAuth[ 'machine_id' ] !== $machineID ) {
			$csAuth = [
				'url'        => $siteURL,
				'machine_id' => $machineID,
				'password'   => wp_generate_password( 48 ),
			];
		}

		$this->storeCsAuth( $csAuth );

		$success = false;
		try {
			$this->machineRegister();
			$this->machineLogin();
			$this->machineEnroll();
			$csAuth = $this->getCsAuth();
			$success = true;
		}
		catch ( Exceptions\MachineRegisterFailedException $e ) {
			error_log( $e->getMessage() );
			$csAuth = $this->getCsAuth();
			unset( $csAuth[ 'machine_registered' ], $csAuth[ 'auth_token' ], $csAuth[ 'auth_expire' ] );
		}
		catch ( Exceptions\MachineLoginFailedException $e ) {
			error_log( $e->getMessage() );
			$csAuth = $this->getCsAuth();
			unset( $csAuth[ 'auth_token' ], $csAuth[ 'auth_expire' ] );
		}
		catch ( Exceptions\MachinePasswordResetFailedException $e ) {
			error_log( $e->getMessage() );
		}
		catch ( Exceptions\MachineEnrollFailedException $e ) {
			error_log( $e->getMessage() );
			$csAuth = $this->getCsAuth();
			unset( $csAuth[ 'machine_enrolled' ] );
		}

		$this->storeCsAuth( $csAuth );

		return $success;
	}

	/**
	 * @throws Exceptions\MachineRegisterFailedException
	 */
	public function machineRegister( bool $forceRegister = false ) {
		$csAuth = $this->getCsAuth();

		if ( empty( $csAuth[ 'machine_registered' ] ) ) {
			try {
				( new Api\MachineRegister() )->run( $csAuth[ 'machine_id' ], $csAuth[ 'password' ] );

				$this->getCon()->fireEvent( 'crowdsec_mach_register', [
					'audit_params' => [
						'machine_id' => $csAuth[ 'machine_id' ],
						'url'        => $csAuth[ 'url' ],
					]
				] );
			}
			catch ( Exceptions\MachineAlreadyRegisteredException $e ) {
			}

			$csAuth[ 'machine_registered' ] = true;

			$this->storeCsAuth( $csAuth );
		}
	}

	/**
	 * @throws Exceptions\MachineLoginFailedException
	 * @throws Exceptions\MachinePasswordResetFailedException
	 */
	public function machineLogin( bool $forceLogin = false ) {
		$csAuth = $this->getCsAuth();

		if ( $csAuth[ 'machine_registered' ] &&
			 ( $forceLogin
			   || empty( $csAuth[ 'auth_token' ] ) || empty( $csAuth[ 'auth_expire' ] )
			   || ( $csAuth[ 'auth_expire' ] - Services::Request()->ts() < 0 ) )
		) {
			try {
				$login = ( new Api\MachineLogin() )->run( $csAuth[ 'machine_id' ], $csAuth[ 'password' ], $this->getScenarios() );
			}
			catch ( Exceptions\MachineLoginFailedException $e ) {
				$newPassword = wp_generate_password( 48 );
				$resetSuccess = ( new Api\MachinePasswordReset() )->run( $csAuth[ 'machine_id' ], $newPassword );
				if ( $resetSuccess ) {
					$csAuth[ 'password' ] = $newPassword;
					$this->storeCsAuth( $csAuth );
				}
				$login = ( new Api\MachineLogin() )->run( $csAuth[ 'machine_id' ], $csAuth[ 'password' ], $this->getScenarios() );
			}

			$csAuth[ 'auth_token' ] = $login[ 'token' ];
			$csAuth[ 'auth_expire' ] = ( new Carbon( $login[ 'expire' ] ) )->timestamp;
			$this->storeCsAuth( $csAuth );

			$this->getCon()->fireEvent( 'crowdsec_auth_acquire', [
				'audit_params' => [
					'expiration' => $login[ 'expire' ], // format: 2022-06-09T14:15:50Z
				]
			] );
		}
	}

	/**
	 * @throws Exceptions\MachineEnrollFailedException
	 */
	public function machineEnroll( bool $forceEnroll = false ) {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$siteURL = Services::WpGeneral()->getWpUrl();
		$csAuth = $this->getCsAuth();

		// Enroll if we have the ID
		$enrollID = preg_replace( '#[^a-z\d]#i', '', (string)$opts->getOpt( 'cs_enroll_id' ) );
		if ( !empty( $enrollID ) && ( empty( $csAuth[ 'machine_enrolled' ] ) || $forceEnroll ) ) {

			$defaultTags = [ 'shield', 'wp', ];
			$defaultName = preg_replace( '#^http(s)?://#i', '', $siteURL );
			if ( $this->getCon()->isPremiumActive() ) {
				$enrollTags = apply_filters( 'shield/crowdsec/enroll_tags', $defaultTags );
				$enrollName = (string)apply_filters( 'shield/crowdsec/enroll_name', $defaultName );
				if ( empty( $enrollName ) ) {
					$enrollName = $defaultName;
				}
			}
			else {
				$enrollTags = $defaultTags;
				$enrollName = $defaultName;
			}

			( new Api\MachineEnroll( $csAuth[ 'auth_token' ] ) )->run(
				$enrollID,
				$enrollName,
				is_array( $enrollTags ) ? $enrollTags : $defaultTags
			);
			$csAuth[ 'machine_enrolled' ] = true;
			$this->storeCsAuth( $csAuth );

			$this->getCon()->fireEvent( 'crowdsec_mach_enroll', [
				'audit_params' => [
					'id'   => $enrollID,
					'name' => $enrollName,
				]
			] );
		}
	}

	private function getScenarios() :array {
		$scenarios = $this->getCon()
						  ->getModule_License()
						  ->getLicenseHandler()
						  ->getLicense()->crowdsec[ 'scenarios' ] ?? [];
		if ( $this->getCon()->isPremiumActive() ) {
			$filteredScenarios = apply_filters( 'shield/crowdsec/login_scenarios', $scenarios );
			if ( !empty( $filteredScenarios ) && is_array( $filteredScenarios ) ) {
				$scenarios = $filteredScenarios;
			}
		}

		return empty( $scenarios ) ? $this->getOptions()->getDef( 'crowdsec' )[ 'scenarios' ][ 'free' ] : $scenarios;
	}

	private function getCsAuth() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getCrowdSecCon()->cfg->cs_auths[ Services::WpGeneral()->getWpUrl() ] ?? [];
	}

	private function storeCsAuth( array $csAuth ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$cfg = $mod->getCrowdSecCon()->cfg;

		$url = $csAuth[ 'url' ];
		if ( ( $cfg->cs_auths[ $url ] ?? [] ) !== $csAuth ) {
			$auths = $cfg->cs_auths;
			$auths[ $url ] = $csAuth;
			$cfg->cs_auths = array_filter(
				$auths,
				function ( $auth ) {
					return empty( $auth[ 'auth_expire' ] )
						   || ( Services::Request()->ts() - $auth[ 'auth_expire' ] < WEEK_IN_SECONDS*4 );
				}
			);

			$mod->getCrowdSecCon()->storeCfg();
		}
	}
}