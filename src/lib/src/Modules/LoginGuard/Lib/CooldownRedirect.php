<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class CooldownRedirect
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard
 */
class CooldownRedirect {

	use Modules\ModConsumer;

	public function run() {
		add_action( 'wp_loaded', [ $this, 'onWpLoaded' ] );
	}

	public function onWpLoaded() {
		$oReq = Services::Request();
		$oCooldownFile = ( new LoginGuard\Lib\CooldownFlagFile() )->setMod( $this->getMod() );
		if ( !$oReq->isPost() && Services::WpGeneral()->isLoginUrl()
			 && $oCooldownFile->isWithinCooldownPeriod()
			 && !Services::WpUsers()->isUserLoggedIn()
			 && $oReq->query( 'cooldown_bypass' ) != 1 ) {
			$this->renderCooldownPage();
		}
	}

	private function renderCooldownPage() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		$nTimeRemaining = ( new LoginGuard\Lib\CooldownFlagFile() )
			->setMod( $oMod )
			->getCooldownRemaining();
		$aData = [
			'strings' => [
				'title'          => __( "The login page is protected against too many login attempts.", 'wp-simple-firewall' ),
				'lines'          => [
					__( 'If you attempt to login again too quickly you may be blocked from accessing this site entirely.', 'wp-simple-firewall' ),
					__( 'If you share this website with others, you may also block their access to the site.', 'wp-simple-firewall' ),
					__( 'To ignore this message and return to the login page, please check the box and click continue.', 'wp-simple-firewall' ),
				],
				'understand'     => __( 'I understand I may block my access to the site.', 'wp-simple-firewall' ),
				'time_remaining' => __( 'Seconds remaining', 'wp-simple-firewall' ),
				'button'         => __( 'Proceed To Login Page', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'remaining' => $nTimeRemaining,
				'login_url' => Services::WpGeneral()->getLoginUrl(),
			],
			'flags'   => [
			],
		];
		Services::WpGeneral()
				->wpDie(
					$oMod->renderTemplate( '/snippets/cooldown_login_block.twig', $aData, true )
				);
	}
}
