<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

class BlockRequest {

	use ModConsumer;

	public function run() {
		if ( $this->isBlocked() ) {

			if ( $this->isAutoUnBlocked() ) {
				// TODO: flash message
				Services::Response()->redirectToAdmin();
			}

			// don't log killed requests
			add_filter( $this->getCon()->prefix( 'is_log_traffic' ), '__return_false' );
			$this->getCon()->fireEvent( 'conn_kill' );
			$this->renderKillPage();
		}
	}

	/**
	 * @return bool
	 */
	private function isBlocked() {
		return ( new IPs\Components\QueryIpBlock() )
			->setMod( $this->getMod() )
			->setIp( Services::IP()->getRequestIp() )
			->run();
	}

	/**
	 * @return bool
	 */
	private function isAutoUnBlocked() {
		return ( new AutoUnblock() )
			->setMod( $this->getMod() )
			->run();
	}

	private function renderKillPage() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $mod->getOptions();
		$con = $this->getCon();
		$oLoginMod = $con->getModule_LoginGuard();

		$sUniqId = 'uau'.uniqid();

		$sIP = Services::IP()->getRequestIp();
		$nTimeRemaining = max( floor( $opts->getAutoExpireTime()/60 ), 0 );

		$user = Services::WpUsers()->getCurrentWpUser();
		$bCanUauGasp = $opts->isEnabledAutoVisitorRecover() && $opts->getCanIpRequestAutoUnblock( $sIP );
		$bCanUauMagic = $opts->isEnabledMagicEmailLinkRecover() &&
						$user instanceof \WP_User
						&& $opts->getCanRequestAutoUnblockEmailLink( $user );
		$bCanAutoRecover = $bCanUauGasp || $bCanUauMagic;

		$aData = [
			'strings' => [
				'title'   => sprintf( __( "You've been blocked by the %s plugin", 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$con->getPluginSpec()[ 'meta' ][ 'url_repo_home' ],
						$con->getHumanName()
					)
				),
				'lines'   => [
					sprintf( __( 'Time remaining on black list: %s', 'wp-simple-firewall' ),
						sprintf( _n( '%s minute', '%s minutes', $nTimeRemaining, 'wp-simple-firewall' ), $nTimeRemaining )
					),
					sprintf( __( 'You tripped the security plugin defenses a total of %s times making you a suspect.', 'wp-simple-firewall' ), $opts->getOffenseLimit() ),
					sprintf( __( 'If you believe this to be in error, please contact the site owner and quote your IP address below.', 'wp-simple-firewall' ) ),
				],
				'your_ip' => 'Your IP address',
				'unblock' => [
					'title'   => __( 'Auto-Unblock Your IP', 'wp-simple-firewall' ),
					'you_can' => __( 'You can automatically unblock your IP address by clicking the button below.', 'wp-simple-firewall' ),
					'button'  => __( 'Unblock My IP Address', 'wp-simple-firewall' ),
				],
			],
			'content' => [
				'email_unblock' => $this->renderEmailMagicLinkContent()
			],
			'vars'    => [
				'nonce'        => $mod->getNonceActionData( 'uau' ),
				'ip'           => $sIP,
				'gasp_element' => $mod->renderTemplate(
					'snippets/gasp_js.php',
					[
						'sCbName'   => $oLoginMod->getGaspKey(),
						'sLabel'    => $oLoginMod->getTextImAHuman(),
						'sAlert'    => $oLoginMod->getTextPleaseCheckBox(),
						'sMustJs'   => __( 'You MUST enable Javascript to be able to login', 'wp-simple-firewall' ),
						'sUniqId'   => $sUniqId,
						'sUniqElem' => 'icwp_wpsf_login_p'.$sUniqId,
						'strings'   => [
							'loading' => __( 'Loading', 'wp-simple-firewall' )
						]
					]
				),
			],
			'flags'   => [
				'is_autorecover'    => $bCanAutoRecover,
				'is_uaug_permitted' => $bCanUauGasp,
				'is_uaum_permitted' => $bCanUauMagic,
			],
		];
		Services::WpGeneral()
				->wpDie(
					$mod->renderTemplate( '/pages/block/blocklist_die.twig', $aData, true )
				);
	}

	/**
	 * @return string
	 */
	private function renderEmailMagicLinkContent() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $mod->getOptions();
		$con = $this->getCon();

		$content = '';

		$user = Services::WpUsers()->getCurrentWpUser();
		if ( $user instanceof \WP_User &&
			 $opts->isEnabledMagicEmailLinkRecover()
			 && $opts->getCanRequestAutoUnblockEmailLink( $user ) ) {

			if ( apply_filters( $con->prefix( 'can_user_magic_link' ), true ) ) {
				$content = $mod->renderTemplate(
					'/pages/block/magic_link.twig',
					[
						'flags'   => [
						],
						'hrefs'   => [
							'unblock' => add_query_arg(
								array_merge(
									$mod->getNonceActionData( 'uaum-init-'.substr( sha1( $user->user_login ), 0, 6 ) ),
									[
										'ip' => Services::IP()->getRequestIp()
									]
								),
								Services::WpGeneral()->getHomeUrl()
							)
						],
						'vars'    => [
							'email' => Obfuscate::Email( $user->user_email )
						],
						'strings' => [
							'you_may'        => __( 'You can automatically unblock your IP address by clicking the link below.', 'wp-simple-firewall' ),
							'this_will_send' => __( 'Clicking the link will send you an email letting you unblock your IP address.', 'wp-simple-firewall' ),
							'assumes_email'  => __( 'This assumes that your WordPress site has been properly configured to send email - many are not.', 'wp-simple-firewall' ),
							'dont_receive'   => __( "If you don't receive the email, check your spam and contact your site admin.", 'wp-simple-firewall' ),
							'limit_60'       => __( "You may only use this link once every 60 minutes. If you're repeatedly blocked, ask your site admin to review the audit trail to determine the cause.", 'wp-simple-firewall' ),
							'same_browser'   => __( "When you click the link from your email, it must open up in this web browser.", 'wp-simple-firewall' ),
							'click_to_send'  => __( 'Send Auto-Unblock Link To My Email', 'wp-simple-firewall' )
						],
					]
				);
			}
		}

		return $content;
	}
}