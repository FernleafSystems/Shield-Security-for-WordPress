<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockCrowdsec extends ExecOnceModConsumer {

	protected function canRun() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return $this->getCon()->this_req->is_ip_crowdsec_blocked && $opts->isEnabledCrowdSecAutoVisitorUnblock();
	}

	protected function run() {
		try {
			$unblocked = $this->processAutoUnblockRequest();
		}
		catch ( \Exception $e ) {
			$unblocked = false;
		}

		if ( $unblocked ) {
			Services::Response()->redirectToHome();
		}
	}

	/**
	 * @throws \Exception
	 */
	private function processAutoUnblockRequest() :bool {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$unblocked = false;

		$ip = $req->ip();
		if ( empty( $ip ) ) {
			throw new \Exception( 'No IP' );
		}

		if ( $req->post( 'action' ) == $mod->getCon()->prefix() && $req->post( 'exec' ) == 'uau-cs-'.$ip ) {

			if ( check_admin_referer( 'uau-cs-'.$ip, 'exec_nonce' ) !== 1 ) {
				throw new \Exception( 'Nonce failed' );
			}
			if ( strlen( (string)$req->post( 'icwp_wpsf_login_email' ) ) > 0 ) {
				throw new \Exception( 'Email should not be provided in honeypot' );
			}

			if ( !$opts->getCanIpRequestAutoUnblock( $ip ) ) {
				throw new \Exception( 'IP already processed in the last 1hr' );
			}

			{
				$existing = $opts->getAutoUnblockIps();
				$existing[ $ip ] = Services::Request()->ts();
				$opts->setOpt( 'autounblock_ips',
					array_filter( $existing, function ( $ts ) {
						return Services::Request()
									   ->carbon()
									   ->subHours( 1 )->timestamp < $ts;
					} )
				);
			}

			$csRecord = ( new IPs\DB\CrowdSec\LoadCrowdSecRecords() )
				->setMod( $mod )
				->setIP( $ip )
				->loadRecord();
			if ( !empty( $csRecord ) ) {
				$mod->getDbH_CrowdSec()
					->getQueryUpdater()
					->updateById( $csRecord->id, [
						'auto_unblock_at' => $req->ts()
					] );
				$unblocked = true;
			}
		}

		return $unblocked;
	}

	/**
	 * @throws \Exception
	 */
	private function processUserMagicLink() :bool {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		$req = Services::Request();

		$unblocked = false;

		if ( $opts->isEnabledMagicEmailLinkRecover()
			 && $req->query( 'action' ) == $mod->getCon()->prefix()
			 && strpos( $req->query( 'exec' ), 'uaum-' ) === 0 ) {

			if ( check_admin_referer( $req->request( 'exec' ), 'exec_nonce' ) !== 1 ) {
				throw new \Exception( 'Nonce failed' );
			}

			$user = Services::WpUsers()->getCurrentWpUser();
			if ( !$user instanceof \WP_User ) {
				throw new \Exception( 'There is no user currently logged-in.' );
			}

			$linkParts = explode( '-', $req->query( 'exec' ), 3 );

			if ( !hash_equals( substr( sha1( $user->user_login ), 0, 6 ), $linkParts[ 2 ] ) ) {
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
						array_filter( $existing, function ( $ts ) {
							return Services::Request()
										   ->carbon()
										   ->subHours( 1 )->timestamp < $ts;
						} )
					);
				}
				http_response_code( 200 );
				die();
			}
			elseif ( $linkParts[ 1 ] === 'go' ) {
				( new IPs\Lib\Ops\DeleteIp() )
					->setMod( $mod )
					->setIP( Services::IP()->getRequestIp() )
					->fromBlacklist();
				( new IPs\Lib\Bots\BotSignalsRecord() )
					->setMod( $this->getMod() )
					->setIP( Services::IP()->getRequestIp() )
					->delete();
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
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$user = Services::WpUsers()->getCurrentWpUser();

		$mod->getEmailProcessor()->sendEmailWithTemplate(
			'/email/uaum_init.twig',
			$user->user_email,
			__( 'Automatic IP Unblock Request', 'wp-simple-firewall' ),
			[
				'flags'   => [
					'show_login_link' => !$this->getCon()->isRelabelled()
				],
				'vars'    => [
				],
				'hrefs'   => [
					'unblock' => add_query_arg(
						array_merge(
							$mod->getNonceActionData( 'uaum-go-'.substr( sha1( $user->user_login ), 0, 6 ) ),
							[
								'ip' => Services::IP()->getRequestIp()
							]
						),
						Services::WpGeneral()->getHomeUrl()
					)
				],
				'strings' => [
					'looks_like'       => __( "It looks like you've been blocked and have clicked to have your IP address removed from the blocklist.", 'wp-simple-firewall' ),
					'please_click'     => __( 'Please click the link provided below to do so.', 'wp-simple-firewall' ),
					'details'          => __( 'Details', 'wp-simple-firewall' ),
					'unblock_my_ip'    => sprintf( '%s: %s',
						__( 'Unblock My IP', 'wp-simple-firewall' ), Services::IP()->getRequestIp() ),
					'or_copy'          => __( 'Or Copy-Paste', 'wp-simple-firewall' ),
					'details_url'      => sprintf( '%s: %s',
						__( 'URL', 'wp-simple-firewall' ), Services::WpGeneral()->getHomeUrl() ),
					'details_username' => sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $user->user_login ),
					'details_ip'       => sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), Services::IP()
																											   ->getRequestIp() ),
					'important'        => __( 'Important', 'wp-simple-firewall' ),
					'imp_limit'        => __( "You'll need to wait for a further 60 minutes if your IP address gets blocked again.", 'wp-simple-firewall' ),
					'imp_browser'      => __( "This link will ONLY work if it opens in the same web browser that you used to request this email.", 'wp-simple-firewall' ),
				]
			]
		);
	}
}