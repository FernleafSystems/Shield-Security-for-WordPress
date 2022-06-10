<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec;

use Carbon\Carbon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
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

	/**
	 * @throws \Exception
	 */
	public function isReady() :bool {
		$this->login();
		return in_array( $this->getAuthState(), [
			self::STATE_NO_ENROLL_ID,
			self::STATE_MACH_NOT_ENROLLED,
			self::STATE_READY
		] );
	}

	/**
	 * @throws \Exception
	 */
	public function getAuthorizationToken() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$this->isReady();
		return $mod->getCrowdSecCon()->cfg->cs_auths[ Services::WpGeneral()->getWpUrl() ][ 'auth_token' ];
	}

	/**
	 * @throws Exceptions\FailedToDownloadDecisionsStreamException
	 * @throws \Exception
	 */
	public function downloadDecisions() :array {
		return ( new Api\DecisionsDownload( $this->getAuthorizationToken() ) )->run();
//		return [
//			'new' => [
//				[ "start_ip" => '90.250.11.231', ]
//			]
//		];
	}

	public function getAuthState() :string {
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
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		$mod->getCrowdSecCon()->execute();
		$crowdSecSpec = $this->getOptions()->getDef( 'crowdsec' );

		$siteURL = Services::WpGeneral()->getWpUrl();
		$csAuth = $mod->getCrowdSecCon()->cfg->cs_auths[ $siteURL ] ?? [];

		if ( ( $csAuth[ 'url' ] ?? '' ) !== $siteURL ) {
			$csAuth = [
				'url' => $siteURL,
			];
		}

		if ( empty( $csAuth[ 'machine_id' ] ) ) {
			$parsed = wp_parse_url( $siteURL );
			$machBase = preg_replace( '#[^a-z\d]#i', '', $parsed[ 'host' ].$parsed[ 'path' ] );
			if ( strlen( $machBase ) >= 48 ) {
				$machBase = substr( $machBase, 0, 48 );
			}
			$csAuth[ 'machine_id' ] = $machBase.wp_generate_password( 48 - strlen( $machBase ), false );
			$csAuth[ 'password' ] = wp_generate_password( 48, true );
		}

		$this->storeCsAuth( $csAuth );

		$success = false;
		try {
			if ( empty( $csAuth[ 'machine_registered' ] ) ) {
				( new Api\MachineRegister() )->run( $csAuth[ 'machine_id' ], $csAuth[ 'password' ] );
				$csAuth[ 'machine_registered' ] = true;

				$this->getCon()->fireEvent( 'crowdsec_mach_register', [
					'audit_params' => [
						'machine_id' => $csAuth[ 'machine_id' ],
						'url'        => $csAuth[ 'url' ],
					]
				] );
			}

			$this->storeCsAuth( $csAuth );

			if ( empty( $csAuth[ 'auth_token' ] ) || empty( $csAuth[ 'auth_expire' ] )
				 || ( $csAuth[ 'auth_expire' ] - Services::Request()->ts() < 0 )
			) {
				$scenarios = $crowdSecSpec[ 'scenarios' ][ 'free' ];
				if ( $this->getCon()->isPremiumActive() ) {
					$scenarios = array_merge( $scenarios, $crowdSecSpec[ 'scenarios' ][ 'pro' ] );
					$filteredScenarios = apply_filters( 'shield/crowdsec_login_scenarios', $scenarios );
					if ( !empty( $filteredScenarios ) && is_array( $filteredScenarios ) ) {
						$scenarios = $filteredScenarios;
					}
				}
				$login = ( new Api\MachineLogin() )->run( $csAuth[ 'machine_id' ], $csAuth[ 'password' ], $scenarios );
				$csAuth[ 'auth_token' ] = $login[ 'token' ];
				$csAuth[ 'auth_expire' ] = ( new Carbon( $login[ 'expire' ] ) )->timestamp;

				$this->getCon()->fireEvent( 'crowdsec_auth_acquire', [
					'audit_params' => [
						'expiration' => $login[ 'expire' ], // format: 2022-06-09T14:15:50Z
					]
				] );
			}

			$this->storeCsAuth( $csAuth );

			// Enroll if we have the ID
			$enrollID = preg_replace( '#[^a-z\d]#i', '', (string)$opts->getOpt( 'cs_enroll_id' ) );
			if ( !empty( $enrollID ) && empty( $csAuth[ 'machine_enrolled' ] ) ) {

				$defaultTags = [ 'shield', 'wp', ];
				$defaultName = preg_replace( '#^http(s)?://#i', '', $siteURL );
				if ( $this->getCon()->isPremiumActive() ) {
					$enrollTags = apply_filters( 'shield/crowdsec_enroll_tags', $defaultTags );
					$enrollName = (string)apply_filters( 'shield/crowdsec_enroll_name', $defaultName );
					if ( empty( $enrollName ) ) {
						$enrollName = $defaultName;
					}
				}
				else {
					$enrollTags = $defaultTags;
					$enrollName = $defaultName;
				}

				( new Api\MachineEnroll( $csAuth[ 'auth_token' ] ) )
					->run(
						$enrollID,
						$enrollName,
						is_array( $enrollTags ) ? $enrollTags : $defaultTags
					);

				$this->getCon()->fireEvent( 'crowdsec_mach_enroll', [
					'audit_params' => [
						'id'   => $enrollID,
						'name' => $enrollName,
					]
				] );

				$csAuth[ 'machine_enrolled' ] = true;
			}

			$this->storeCsAuth( $csAuth );

			$success = true;
		}
		catch ( Exceptions\FailedToMachineRegisterException $e ) {
			error_log( $e->getMessage() );
			unset( $csAuth[ 'machine_registered' ], $csAuth[ 'auth_token' ], $csAuth[ 'auth_expire' ] );
		}
		catch ( Exceptions\FailedToMachineLoginException $e ) {
			error_log( $e->getMessage() );
			unset( $csAuth[ 'auth_token' ], $csAuth[ 'auth_expire' ] );
		}
		catch ( Exceptions\FailedToMachineEnrollException $e ) {
			error_log( $e->getMessage() );
			unset( $csAuth[ 'machine_enrolled' ] );
		}

		return $success;
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

			$this->getOptions()->setOpt( 'crowdsec_cfg', $cfg->getRawData() );
			$this->getMod()->saveModOptions();
		}
	}
}