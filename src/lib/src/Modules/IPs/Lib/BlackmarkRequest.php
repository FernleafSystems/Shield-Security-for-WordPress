<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BlackmarkRequest {

	use ModConsumer;

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		$oMod->loadOffenseTracker()->setIfCommit( true );

		$oCon = $this->getCon();
		add_filter( $oCon->prefix( 'firewall_die_message' ), [ $this, 'augmentFirewallDieMessage' ] );
		add_action( $oCon->prefix( 'pre_plugin_shutdown' ), function () {
			$this->processOffense();
		} );
		add_action( 'shield_security_offense', [ $this, 'processCustomShieldOffense' ], 10, 3 );
	}

	private function processOffense() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		$oTracker = $oMod->loadOffenseTracker();
		if ( !$this->getCon()->plugin_deleting && $oTracker->hasVisitorOffended() && $oTracker->isCommit() ) {
			( new IPs\Components\ProcessOffense() )
				->setMod( $oMod )
				->setIp( Services::IP()->getRequestIp() )
				->run();
		}
	}

	/**
	 * @param array $aMessages
	 * @return array
	 */
	public function augmentFirewallDieMessage( $aMessages ) {
		if ( !is_array( $aMessages ) ) {
			$aMessages = [];
		}

		$aMessages[] = sprintf( '<p>%s</p>', sprintf(
			$this->getMod()->getTextOpt( 'text_remainingtrans' ),
			( new IPs\Components\QueryRemainingOffenses() )
				->setMod( $this->getMod() )
				->setIP( Services::IP()->getRequestIp() )
				->run()
		) );

		return $aMessages;
	}

	/**
	 * Allows 3rd parties to trigger Shield offenses
	 * @param string $sMessage
	 * @param int    $nOffenseCount
	 * @param bool   $bIncludeLoggedIn
	 */
	public function processCustomShieldOffense( $sMessage, $nOffenseCount = 1, $bIncludeLoggedIn = true ) {
		if ( $this->getCon()->isPremiumActive() ) {
			if ( empty( $sMessage ) ) {
				$sMessage = __( 'No custom message provided.', 'wp-simple-firewall' );
			}

			if ( $bIncludeLoggedIn || !did_action( 'init' ) || !Services::WpUsers()->isUserLoggedIn() ) {
				$this->getCon()
					 ->fireEvent(
						 'custom_offense',
						 [
							 'audit'         => [ 'message' => $sMessage ],
							 'offense_count' => $nOffenseCount
						 ]
					 );
			}
		}
	}
}