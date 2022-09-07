<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Services\Services;

class AutoUnblockMagicLink extends BaseAutoUnblockShield {

	public function isUnblockAvailable() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledMagicEmailLinkRecover() && parent::isUnblockAvailable();
	}

	protected function run() {
		$req = Services::Request();
		try {
			if ( $req->request( 'action' ) == $this->getCon()->prefix()
				 && strpos( $req->request( 'exec' ), 'uaum-' ) === 0 ) {

				if ( wp_verify_nonce( $req->request( 'exec_nonce' ), $req->request( 'exec' ) ) !== 1 ) {
					throw new \Exception( 'Nonce failed' );
				}
				$user = Services::WpUsers()->getCurrentWpUser();
				if ( !$user instanceof \WP_User ) {
					throw new \Exception( 'There is no user currently logged-in.' );
				}
				// Then verify that the part of the nonce action linked to the user login is valid
				$linkParts = explode( '-', $req->request( 'exec' ), 3 );
				if ( !hash_equals( substr( sha1( $user->user_login ), 0, 6 ), $linkParts[ 2 ] ) ) {
					throw new \Exception( 'Users do not match.' );
				}
				$reqIP = $req->request( 'ip' );
				if ( empty( $reqIP ) || !Services::IP()->checkIp( $reqIP, $this->getCon()->this_req->ip ) ) {
					throw new \Exception( 'IP does not match.' );
				}

				$this->timingChecks();

				if ( $req->isPost() && $linkParts[ 1 ] === 'init' ) {

					$this->sendMagicLinkEmail();
					http_response_code( 200 );
					die();
				}
				elseif ( $req->isGet() && $linkParts[ 1 ] === 'go' ) {

					$this->updateLastAttemptAt();
					if ( $this->unblockIP() ) {
						Services::Response()->redirectToHome();
					}
				}
				else {
					throw new \Exception( 'Not a supported UAUM action.' );
				}
			}
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function sendMagicLinkEmail() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		$user = Services::WpUsers()->getCurrentWpUser();

		$ip = $this->getCon()->this_req->ip;
		$homeURL = Services::WpGeneral()->getHomeUrl();

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
								'ip' => $ip
							]
						),
						$homeURL
					)
				],
				'strings' => [
					'looks_like'       => __( "It looks like you've been blocked and have clicked to have your IP address removed from the blocklist.", 'wp-simple-firewall' ),
					'please_click'     => __( 'Please click the link provided below to do so.', 'wp-simple-firewall' ),
					'details'          => __( 'Details', 'wp-simple-firewall' ),
					'unblock_my_ip'    => sprintf( '%s: %s', __( 'Unblock My IP', 'wp-simple-firewall' ), $ip ),
					'or_copy'          => __( 'Or Copy-Paste', 'wp-simple-firewall' ),
					'details_url'      => sprintf( '%s: %s', __( 'URL', 'wp-simple-firewall' ), $homeURL ),
					'details_username' => sprintf( '%s: %s', __( 'Username', 'wp-simple-firewall' ), $user->user_login ),
					'details_ip'       => sprintf( '%s: %s', __( 'IP Address', 'wp-simple-firewall' ), $ip ),
					'important'        => __( 'Important', 'wp-simple-firewall' ),
					'imp_limit'        => __( "You'll need to wait for a further 60 minutes if your IP address gets blocked again.", 'wp-simple-firewall' ),
					'imp_browser'      => __( "This link will ONLY work if it opens in the same web browser that you used to request this email.", 'wp-simple-firewall' ),
				]
			]
		);
	}
}