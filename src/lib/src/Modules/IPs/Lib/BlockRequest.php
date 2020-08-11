<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BlockRequest {

	use ModConsumer;

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		$bIpBlocked = ( new IPs\Components\QueryIpBlock() )
						  ->setMod( $oMod )
						  ->setIp( Services::IP()->getRequestIp() )
						  ->run()
					  &&
					  !( new AutoUnblock() )
						  ->setMod( $this->getMod() )
						  ->run();

		if ( $bIpBlocked ) {
			// don't log killed requests
			add_filter( $this->getCon()->prefix( 'is_log_traffic' ), '__return_false' );
			$this->getCon()->fireEvent( 'conn_kill' );
			$this->renderKillPage();
		}
	}

	private function renderKillPage() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();
		$oCon = $this->getCon();
		$oLoginMod = $oCon->getModule_LoginGuard();

		$sUniqId = 'uau'.uniqid();

		$sIp = Services::IP()->getRequestIp();
		$nTimeRemaining = max( floor( $oOpts->getAutoExpireTime()/60 ), 0 );
		$aData = [
			'strings' => [
				'title'   => sprintf( __( "You've been blocked by the %s plugin", 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$oCon->getPluginSpec()[ 'meta' ][ 'url_repo_home' ],
						$oCon->getHumanName()
					)
				),
				'lines'   => [
					sprintf( __( 'Time remaining on black list: %s', 'wp-simple-firewall' ),
						sprintf( _n( '%s minute', '%s minutes', $nTimeRemaining, 'wp-simple-firewall' ), $nTimeRemaining )
					),
					sprintf( __( 'You tripped the security plugin defenses a total of %s times making you a suspect.', 'wp-simple-firewall' ), $oOpts->getOffenseLimit() ),
					sprintf( __( 'If you believe this to be in error, please contact the site owner and quote your IP address below.', 'wp-simple-firewall' ) ),
				],
				'your_ip' => 'Your IP address',
				'unblock' => [
					'title'   => __( 'Auto-Unblock Your IP', 'wp-simple-firewall' ),
					'you_can' => __( 'You can automatically unblock your IP address by clicking the button below.', 'wp-simple-firewall' ),
					'button'  => __( 'Unblock My IP Address', 'wp-simple-firewall' ),
				],
			],
			'vars'    => [
				'nonce'        => $oMod->getNonceActionData( 'uau' ),
				'ip'           => $sIp,
				'gasp_element' => $oMod->renderTemplate(
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
				'is_autorecover'   => $oOpts->isEnabledAutoVisitorRecover(),
				'is_uau_permitted' => $oOpts->getCanIpRequestAutoUnblock( $sIp ),
			],
		];
		Services::WpGeneral()
				->wpDie(
					$oMod->renderTemplate( '/snippets/blacklist_die.twig', $aData, true )
				);
	}
}