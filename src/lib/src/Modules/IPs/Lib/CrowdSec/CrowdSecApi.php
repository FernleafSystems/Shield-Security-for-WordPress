<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\InstallationID;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CrowdSecApi {

	use PluginControllerConsumer;

	public const MAX_FAILED_LOGINS = 5;
	public const STATE_NO_URL = 'no_url';
	public const STATE_INVALID_URL = 'invalid_url';
	public const STATE_NO_MACHINE_ID = 'no_mach_id';
	public const STATE_NO_PASSWORD = 'no_password';
	public const STATE_MACHINE_NOT_REGISTERED = 'mach_not_registered';
	public const STATE_NO_AUTH_TOKEN = 'no_auth_token';
	public const STATE_NO_AUTH_EXPIRE = 'no_auth_expire';
	public const STATE_AUTH_EXPIRED = 'auth_expired';
	public const STATE_READY_NO_ENROLL_ID = 'ready_no_enroll_id';
	public const STATE_READY_MACH_NOT_ENROLLED = 'ready_mach_not_enrolled';
	public const STATE_READY_COMPLETE = 'ready_complete';

	public function isReady() :bool {
		$this->login();
		return \in_array( $this->getAuthStatus(), [
			self::STATE_READY_NO_ENROLL_ID,
			self::STATE_READY_MACH_NOT_ENROLLED,
			self::STATE_READY_COMPLETE
		] );
	}

	public function clearEnrollment() :void {
		$auth = $this->getCsAuth();
		unset( $auth[ 'machine_enrolled' ] );
		$this->storeCsAuth( $auth );
	}

	public function getAuthorizationToken() :string {
		return $this->getCsAuth()[ 'auth_token' ] ?? '';
	}

	public function getMachineID() :string {
		return $this->getCsAuth()[ 'machine_id' ] ?? '';
	}

	public function getAuthStatus() :string {
		$csAuth = $this->getCsAuth();

		if ( empty( $csAuth[ 'url' ] ) ) {
			$state = self::STATE_NO_URL;
		}
		elseif ( $csAuth[ 'url' ] !== Services::WpGeneral()->getWpUrl() ) {
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
		elseif ( empty( self::con()->opts->optGet( 'cs_enroll_id' ) ) ) {
			$state = self::STATE_READY_NO_ENROLL_ID;
		}
		elseif ( empty( $csAuth[ 'machine_enrolled' ] ) ) {
			$state = self::STATE_READY_MACH_NOT_ENROLLED;
		}
		else {
			$state = self::STATE_READY_COMPLETE;
		}

		return $state;
	}

	public function login() :bool {
		$success = false;
		$clearAuthStartAt = true;

		try {
			$this->authStart();
			$this->machineRegister();
			$this->machineLogin();
			$this->machineEnroll();
			$success = true;
		}
		catch ( Exceptions\AuthenticationInProgressException $e ) {
			$clearAuthStartAt = false;
		}
		catch ( Exceptions\MachineRegisterFailedException $e ) {
			$fieldsToClear = [
				'machine_id',
				'machine_registered',
				'password',
				'machine_enrolled',
				'auth_token',
				'auth_expire',
			];
		}
		catch ( Exceptions\MachineLoginFailedException  $e ) {
			$fieldsToClear = [
				'failed_login_count',
				'machine_id',
				'machine_registered',
				'password',
				'machine_enrolled',
				'auth_token',
				'auth_expire',
			];
		}
		catch ( Exceptions\MachineEnrollFailedException $e ) {
			$fieldsToClear = [
				'machine_enrolled',
			];
		}
		catch ( \Exception $e ) {
		}
		finally {
			if ( !empty( $e ) ) {
				error_log( '[CROWDSEC EXCEPTION] '.$e->getMessage() );
			}
			if ( empty( $auth ) ) {
				$auth = $this->getCsAuth();
			}
			if ( $clearAuthStartAt ) {
				$fieldsToClear[] = 'auth_start_at';
			}

			if ( !empty( $fieldsToClear ) ) {
				foreach ( $fieldsToClear as $field ) {
					unset( $auth[ $field ] );
				}
			}

			$this->storeCsAuth( $auth );
		}

		return $success;
	}

	/**
	 * @throws Exceptions\AuthenticationInProgressException
	 */
	public function authStart() {
		$now = Services::Request()->ts();
		$auth = $this->getCsAuth();
		if ( !empty( $auth[ 'auth_start_at' ] ) && $now - 30 < $auth[ 'auth_start_at' ] ) {
			throw new Exceptions\AuthenticationInProgressException( 'Authentication is already in progress' );
		}

		$auth[ 'auth_start_at' ] = $now;
		$this->storeCsAuth( $auth );

		$siteURL = Services::WpGeneral()->getWpUrl();
		if ( ( $auth[ 'url' ] ?? '' ) !== $siteURL ) {
			$auth = [
				'auth_start_at' => $now,
				'url'           => $siteURL,
			];
		}
		$this->storeCsAuth( $auth );

		$machStartID = \str_replace( '-', '', ( new InstallationID() )->id() );
		if ( empty( $auth[ 'machine_id' ] ) || \strpos( $auth[ 'machine_id' ], $machStartID ) !== 0 ) {
			$auth = [
				'auth_start_at' => $now,
				'url'           => $siteURL,
				'machine_id'    => $machStartID.strtolower( wp_generate_password( CrowdSecConstants::MACHINE_ID_LENGTH - \strlen( $machStartID ), false ) ),
				'password'      => $this->generateCrowdsecPassword(),
			];
		}

		$this->storeCsAuth( $auth );
	}

	/**
	 * @throws Exceptions\MachineRegisterFailedException
	 */
	public function machineRegister() {
		$auth = $this->getCsAuth();

		if ( empty( $auth[ 'machine_registered' ] ) ) {
			try {
				( new Api\MachineRegister( $this->getApiUserAgent() ) )->run( $auth[ 'machine_id' ], $auth[ 'password' ] );
				$auth[ 'machine_registered' ] = true;
				self::con()->fireEvent( 'crowdsec_mach_register', [
					'audit_params' => [
						'machine_id' => $auth[ 'machine_id' ],
						'url'        => $auth[ 'url' ],
					]
				] );
			}
			catch ( Exceptions\MachineAlreadyRegisteredException $e ) {
				$auth[ 'machine_registered' ] = true;
			}
		}
		$this->storeCsAuth( $auth );
	}

	/**
	 * @throws Exceptions\MachineLoginFailedException
	 * @throws \Carbon\Exceptions\InvalidFormatException
	 */
	public function machineLogin() {
		$auth = $this->getCsAuth();

		if ( !empty( $auth[ 'machine_registered' ] ) &&
			 ( empty( $auth[ 'auth_token' ] ) || empty( $auth[ 'auth_expire' ] )
			   || ( $auth[ 'auth_expire' ] < Services::Request()->ts() ) )
		) {

			if ( !isset( $auth[ 'failed_login_count' ] ) ) {
				$auth[ 'failed_login_count' ] = 0;
			}

			try {
				$login = ( new Api\MachineLogin( $this->getApiUserAgent() ) )
					->run( $auth[ 'machine_id' ], $auth[ 'password' ], $this->getScenarios() );

				$auth[ 'auth_token' ] = $login[ 'token' ];
				$auth[ 'auth_expire' ] = ( new Carbon( $login[ 'expire' ] ) )->subMinute()->timestamp;
				$auth[ 'failed_login_count' ] = 0;
				$this->storeCsAuth( $auth );

				self::con()->fireEvent( 'crowdsec_auth_acquire', [
					'audit_params' => [
						'expiration' => $login[ 'expire' ], // format: 2022-06-09T14:15:50Z
					]
				] );
			}
			catch ( Exceptions\MachineLoginFailedException $e ) {
				$auth[ 'failed_login_count' ]++;
				if ( $auth[ 'failed_login_count' ] >= self::MAX_FAILED_LOGINS ) {
					throw $e;
				}
				$this->storeCsAuth( $auth );
			}
		}
	}

	/**
	 * @throws Exceptions\MachineEnrollFailedException
	 */
	public function machineEnroll() {
		$auth = $this->getCsAuth();

		// Enroll if we have the ID
		$enrollID = \preg_replace( '#[^a-z\d]#i', '', self::con()->opts->optGet( 'cs_enroll_id' ) );
		if ( !empty( $enrollID ) && empty( $auth[ 'machine_enrolled' ] ) ) {

			$defaultTags = [ 'shield', 'wp', ];
			$defaultName = \preg_replace( '#^http(s)?://#i', '', Services::WpGeneral()->getWpUrl() );
			if ( self::con()->isPremiumActive() ) {
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

			( new Api\MachineEnroll( $auth[ 'auth_token' ], $this->getApiUserAgent() ) )->run(
				$enrollID,
				$enrollName,
				\is_array( $enrollTags ) ? $enrollTags : $defaultTags
			);
			$auth[ 'machine_enrolled' ] = true;
			$this->storeCsAuth( $auth );

			self::con()->fireEvent( 'crowdsec_mach_enroll', [
				'audit_params' => [
					'id'   => $enrollID,
					'name' => $enrollName,
				]
			] );
		}
	}

	private function getScenarios() :array {
		$con = self::con();
		$scenarios = $con->comps->license->getLicense()->crowdsec[ 'scenarios' ] ?? [];
		if ( self::con()->isPremiumActive() ) {
			$filteredScenarios = apply_filters( 'shield/crowdsec/login_scenarios', $scenarios );
			if ( !empty( $filteredScenarios ) && \is_array( $filteredScenarios ) ) {
				$scenarios = $filteredScenarios;
			}
		}

		return empty( $scenarios ) ? $con->cfg->configuration->def( 'crowdsec' )[ 'scenarios' ][ 'free' ] : $scenarios;
	}

	private function getCsAuth() :array {
		return $this->getCsAuths()[ Services::WpGeneral()->getWpUrl() ] ?? [];
	}

	private function getCsAuths() :array {
		$auths = Services::WpGeneral()->getOption( self::con()->prefix( 'cs_auths' ) );
		if ( empty( $auths ) || !\is_array( $auths ) ) {
			$auths = self::con()->comps->crowdsec->cfg()->cs_auths;
		}

		if ( !\is_array( $auths ) ) {
			$auths = [];
		}

		$url = Services::WpGeneral()->getWpUrl();
		$auths[ $url ] = \array_merge( [
			'url'              => $url,
			'auth_token'       => '',
			'auth_start_at'    => 0,
			'auth_expire'      => '',
			'machine_enrolled' => false,
			'machine_id'       => '',
			'password'         => '',
		], $auths[ $url ] ?? [] );
		Services::WpGeneral()->updateOption( self::con()->prefix( 'cs_auths' ), $auths );

		return $auths;
	}

	private function storeCsAuth( array $csAuth ) {
		if ( !empty( $csAuth[ 'url' ] ) ) {

			$auths = $this->getCsAuths();
			$auths[ $csAuth[ 'url' ] ] = $csAuth;
			$auths = \array_filter( $auths, function ( $auth ) {
				return empty( $auth[ 'auth_expire' ] )
					   || Services::Request()->ts() - $auth[ 'auth_expire' ] < \WEEK_IN_SECONDS*12;
			} );
			Services::WpGeneral()->updateOption( self::con()->prefix( 'cs_auths' ), $auths );

			$cfg = self::con()->comps->crowdsec->cfg();
			$cfg->cs_auths = $auths;
			self::con()->comps->crowdsec->storeCfg( $cfg );
		}
	}

	public function getApiUserAgent() :string {
		$con = self::con();
		return sprintf( '%s/v%s', $con->isPremiumActive() ? 'ShieldSecurityPro' : 'ShieldSecurity', $con->cfg->version() );
	}

	/**
	 * Length: 32; At least 1 lower, 1 upper, 1 digit.
	 */
	private function generateCrowdsecPassword() :string {
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

		$pass = wp_generate_password( \rand( 10, 20 ), false );
		if ( !\preg_match( '#[a-z]#', $pass ) ) {
			$pass .= \substr( $chars, wp_rand( 0, 25 ), 1 );
		}
		if ( !\preg_match( '#[A-Z]#', $pass ) ) {
			$pass .= \substr( $chars, wp_rand( 26, 51 ), 1 );
		}
		if ( !\preg_match( '#\d#', $pass ) ) {
			$pass .= \substr( $chars, wp_rand( 52, 61 ), 1 );
		}
		return \substr( $pass.wp_generate_password( 22, false ), 0, 32 );
	}
}