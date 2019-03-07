<?php

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\BotTrap;

class ICWP_WPSF_Processor_Bottrap extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Bottrap $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isEnabled404() ) {
			( new BotTrap\Detect404() )
				->setMod( $oFO )
				->run();
		}

		if ( $oFO->isEnabledInvalidUsernames() ) {
			( new BotTrap\InvalidUsername() )
				->setMod( $oFO )
				->run();
		}

		if ( $oFO->isEnabledFailedLogins() ) {
			( new BotTrap\FailedAuthenticate() )
				->setMod( $oFO )
				->run();
		}

		if ( $oFO->isEnabledFakeWebCrawler() ) {
			( new BotTrap\FakeWebCrawler() )
				->setMod( $oFO )
				->run();
		}

		if ( $oFO->isEnabledLinkCheese() ) {
			( new BotTrap\LinkCheese() )
				->setMod( $oFO )
				->run();
		}

		if ( $oFO->isEnabledXmlRpcDetect() ) {
			( new BotTrap\DetectXmlRpc() )
				->setMod( $oFO )
				->run();
		}
	}
}