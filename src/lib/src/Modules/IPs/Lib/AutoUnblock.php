<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblock {

	use ModConsumer;

	/**
	 * This should only be run if the current IP has been verified as being blocked
	 * @return bool - true if IP is unblocked, false otherwise.
	 */
	public function run() {
		try {
			$bUnblocked = $this->processAutoUnblockRequest()
						  || $this->processUserMagicLink();
		}
		catch ( \Exception $oE ) {
			var_dump( $oE->getMessage() );
			$bUnblocked = false;
		}
		return $bUnblocked;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	private function processAutoUnblockRequest() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();
		$req = Services::Request();

		$unblocked = false;

		if ( $oOpts->isEnabledAutoVisitorRecover() && $req->isPost()
			 && $req->request( 'action' ) == $oMod->prefix() && $req->request( 'exec' ) == 'uau' ) {

			if ( check_admin_referer( $req->request( 'exec' ), 'exec_nonce' ) !== 1 ) {
				throw new \Exception( 'Nonce failed' );
			}
			if ( strlen( $req->post( 'icwp_wpsf_login_email' ) ) > 0 ) {
				throw new \Exception( 'Email should not be provided in honeypot' );
			}

			$sIP = Services::IP()->getRequestIp();
			if ( $req->post( 'ip' ) != $sIP ) {
				throw new \Exception( 'IP does not match' );
			}

			$oLoginMod = $this->getCon()->getModule_LoginGuard();
			$sGasp = $req->post( $oLoginMod->getGaspKey() );
			if ( empty( $sGasp ) ) {
				throw new \Exception( 'GASP failed' );
			}

			if ( !$oOpts->getCanIpRequestAutoUnblock( $sIP ) ) {
				throw new \Exception( 'IP already processed in the last 24hrs' );
			}

			{
				$aExistingIps = $oOpts->getAutoUnblockIps();
				$aExistingIps[ $sIP ] = Services::Request()->ts();
				$oOpts->setOpt( 'autounblock_ips',
					array_filter( $aExistingIps, function ( $nTS ) {
						return Services::Request()
									   ->carbon()
									   ->subDays( 1 )->timestamp < $nTS;
					} )
				);
			}

			( new IPs\Lib\Ops\DeleteIp() )
				->setDbHandler( $oMod->getDbHandler_IPs() )
				->setIP( $sIP )
				->fromBlacklist();
			$unblocked = true;
		}

		return $unblocked;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	private function processUserMagicLink() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$unblocked = false;

		if ( $opts->isEnabledMagicEmailLinkRecover()
			 && $req->query( 'action' ) == $mod->prefix()
			 && strpos( $req->query( 'exec' ), 'uaum-' ) === 0 ) {

			if ( check_admin_referer( $req->request( 'exec' ), 'exec_nonce' ) !== 1 ) {
				throw new \Exception( 'Nonce failed' );
			}

			$linkParts = explode( '-', $req->query( 'exec' ), 3 );
			$userLogin = $linkParts[ 2 ];
			if ( empty( $userLogin ) ) {
				throw new \Exception( 'User not provided in request.' );
			}

			$user = Services::WpUsers()->getCurrentWpUser();
			if ( !$user instanceof \WP_User ) {
				throw new \Exception( 'There is no user currently logged-in.' );
			}
			if ( $userLogin !== $user->user_login ) {
				throw new \Exception( 'Users do not match.' );
			}

			if ( $req->query( 'ip' ) !== Services::IP()->getRequestIp() ) {
				throw new \Exception( 'IP does not match.' );
			}

			if ( $linkParts[ 1 ] === 'init' ) {
				if ( !$opts->getCanRequestAutoUnblockEmailLink( $user ) ) {
					throw new \Exception( 'User already processed recently.' );
				}

				$this->sendMagicLinkEmail();
				{
					$existing = $opts->getAutoUnblockEmailIDs();
					$existing[ $user->ID ] = Services::Request()->ts();
					$opts->setOpt( 'autounblock_emailids',
						array_filter( $existing, function ( $nTS ) {
							return Services::Request()
										   ->carbon()
										   ->subHours( 1 )->timestamp < $nTS;
						} )
					);
				}
				wp_die( 'Email sent.' );
			}
			elseif ( $linkParts[ 1 ] === 'go' ) {
				( new IPs\Lib\Ops\DeleteIp() )
					->setDbHandler( $mod->getDbHandler_IPs() )
					->setIP( Services::IP()->getRequestIp() )
					->fromBlacklist();
				$unblocked = true;
			}
			else {
				throw new \Exception( 'Not a supported UAUM action.' );
			}
		}

		return $unblocked;
	}

	/**
	 * @throws \Exception
	 */
	private function sendMagicLinkEmail() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();
		$user = Services::WpUsers()->getCurrentWpUser();

		$mod->getEmailProcessor()
			->sendEmailWithTemplate(
				'/email/uaum_init',
				$user->user_email,
				__( 'Two-Factor Login Verification', 'wp-simple-firewall' ),
				[
					'flags'   => [
						'show_login_link' => !$this->getCon()->isRelabelled()
					],
					'vars'    => [
					],
					'hrefs'   => [
						'unblock' => add_query_arg(
							array_merge(
								$mod->getNonceActionData( 'uaum-go-'.$user->user_login ),
								[
									'ip' => Services::IP()->getRequestIp()
								]
							),
							Services::WpGeneral()->getHomeUrl()
						)
					],
					'strings' => [

					]
				]
			);
	}
}