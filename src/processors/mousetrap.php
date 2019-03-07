<?php

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\MouseTrap;

class ICWP_WPSF_Processor_Mousetrap extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * Resets the object values to be re-used anew
	 */
	public function init() {
		parent::init();
	}

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Mousetrap $oFO */
		$oFO = $this->getMod();

		if ( $oFO->isEnabled404() ) {
			( new MouseTrap\Detect404() )
				->setMod( $oFO )
				->run();
		}

		if ( $oFO->isEnabledInvalidUsernames() ) {
			( new MouseTrap\InvalidUsername() )
				->setMod( $oFO )
				->run();
		}

		if ( $oFO->isEnabledFailedLogins() ) {
			( new MouseTrap\FailedAuthenticate() )
				->setMod( $oFO )
				->run();
		}

		if ( $oFO->isEnabledFakeWebCrawler() ) {
			( new MouseTrap\FakeWebCrawler() )
				->setMod( $oFO )
				->run();
		}

		if ( $oFO->isEnabledLinkCheese() ) {
			( new MouseTrap\LinkCheese() )
				->setMod( $oFO )
				->run();
		}

		if ( $oFO->isEnabledXmlRpcDetect() ) {
			( new MouseTrap\DetectXmlRpc() )
				->setMod( $oFO )
				->run();
		}
	}
}