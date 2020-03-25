<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BlacklistHandler extends OneTimeExecute {

	use ModConsumer;

	protected function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isEnabledAutoBlackList() ) {

			( new IPs\Components\UnblockIpByFlag() )
				->setMod( $oMod )
				->run();

			add_action( 'init', [ $this, 'loadBotDetectors' ] ); // hook in the bot detection

			if ( !$oMod->isVisitorWhitelisted() && !$this->isRequestWhitelisted() && !$oMod->isVerifiedBot() ) {
				( new BlockRequest() )
					->setMod( $oMod )
					->run();
				( new BlackmarkRequest() )
					->setMod( $oMod )
					->run();
			}
		}
	}

	public function loadBotDetectors() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $oMod->getOptions();

		if ( !Services::WpUsers()->isUserLoggedIn() ) {

			if ( !$oMod->isVerifiedBot() ) {
				if ( $oOpts->isEnabledTrackXmlRpc() ) {
					( new IPs\BotTrack\TrackXmlRpc() )
						->setMod( $oMod )
						->run();
				}
				if ( $oOpts->isEnabledTrack404() ) {
					( new IPs\BotTrack\Track404() )
						->setMod( $oMod )
						->run();
				}
				if ( $oOpts->isEnabledTrackLoginFailed() ) {
					( new IPs\BotTrack\TrackLoginFailed() )
						->setMod( $oMod )
						->run();
				}
				if ( $oOpts->isEnabledTrackLoginInvalid() ) {
					( new IPs\BotTrack\TrackLoginInvalid() )
						->setMod( $oMod )
						->run();
				}
				if ( $oOpts->isEnabledTrackFakeWebCrawler() ) {
					( new IPs\BotTrack\TrackFakeWebCrawler() )
						->setMod( $oMod )
						->run();
				}
			}

			/** Always run link cheese regardless of the verified bot or not */
			if ( $oOpts->isEnabledTrackLinkCheese() ) {
				( new IPs\BotTrack\TrackLinkCheese() )
					->setMod( $oMod )
					->run();
			}
		}
	}

	/**
	 * @return bool
	 */
	private function isRequestWhitelisted() {
		/** @var IPs\Options $oOpts */
		$oOpts = $this->getOptions();
		$bWhitelisted = false;
		$aWhitelist = $oOpts->getRequestWhitelistAsRegex();
		if ( !empty( $aWhitelist ) ) {
			$sPath = strtolower( '/'.ltrim( (string)Services::Request()->getPath(), '/' ) );
			foreach ( $aWhitelist as $sRule ) {
				if ( preg_match( $sRule, $sPath ) ) {
					$bWhitelisted = true;
					break;
				}
			}
		}
		return $bWhitelisted;
	}
}