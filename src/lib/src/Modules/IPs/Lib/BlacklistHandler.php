<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class BlacklistHandler {

	use Modules\ModConsumer;
	use Modules\Base\OneTimeExecute;

	protected function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->isEnabledAutoBlackList() ) {

			$oCon = $this->getCon();
			if ( Services::WpGeneral()->isCron() && $oCon->isPremiumActive() ) {
				add_action( $oCon->prefix( 'hourly_cron' ), [ $this, 'runHourlyCron' ] );
			}

			( new IPs\Components\UnblockIpByFlag() )
				->setMod( $mod )
				->run();

			add_action( 'init', [ $this, 'loadBotDetectors' ] ); // hook in the bot detection

			if ( !$mod->isVisitorWhitelisted()
				 && !$this->isRequestWhitelisted() && !$mod->isVerifiedBot() ) {

				// We setup offenses processing immediately but run the blocks on 'init
				( new ProcessOffenses() )
					->setMod( $this->getMod() )
					->run();
				add_action( 'init', function () {
					( new BlockRequest() )
						->setMod( $this->getMod() )
						->run();
				}, -100000 );

			}
		}
	}

	public function loadBotDetectors() {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		if ( !Services::WpUsers()->isUserLoggedIn() ) {

			if ( !$mod->isVerifiedBot() ) {
				if ( $opts->isEnabledTrackXmlRpc() ) {
					( new IPs\BotTrack\TrackXmlRpc() )
						->setMod( $mod )
						->run();
				}
				if ( $opts->isEnabledTrack404() ) {
					( new IPs\BotTrack\Track404() )
						->setMod( $mod )
						->run();
				}
				if ( $opts->isEnabledTrackLoginFailed() ) {
					( new IPs\BotTrack\TrackLoginFailed() )
						->setMod( $mod )
						->run();
				}
				if ( $opts->isEnabledTrackLoginInvalid() ) {
					( new IPs\BotTrack\TrackLoginInvalid() )
						->setMod( $mod )
						->run();
				}
				if ( $opts->isEnabledTrackFakeWebCrawler() ) {
					( new IPs\BotTrack\TrackFakeWebCrawler() )
						->setMod( $mod )
						->run();
				}
			}

			/** Always run link cheese regardless of the verified bot or not */
			if ( $opts->isEnabledTrackLinkCheese() ) {
				( new IPs\BotTrack\TrackLinkCheese() )
					->setMod( $mod )
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

	public function runHourlyCron() {
		( new IPs\Components\ImportIpsFromFile() )
			->setMod( $this->getMod() )
			->run();
	}
}