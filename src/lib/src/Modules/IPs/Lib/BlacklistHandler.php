<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Utilities\Logic\OneTimeExecute;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class BlacklistHandler {

	use Modules\ModConsumer;
	use OneTimeExecute;

	protected function run() {
		/** @var IPs\ModCon $mod */
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
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		if ( !Services::WpUsers()->isUserLoggedIn() ) {

			if ( !$mod->isVerifiedBot() ) {
				if ( $opts->isEnabledTrackXmlRpc() ) {
					( new IPs\BotTrack\TrackXmlRpc() )
						->setMod( $mod )
						->execute();
				}
				if ( $opts->isEnabledTrack404() ) {
					( new IPs\BotTrack\Track404() )
						->setMod( $mod )
						->execute();
				}
				if ( $opts->isEnabledTrackLoginFailed() ) {
					( new IPs\BotTrack\TrackLoginFailed() )
						->setMod( $mod )
						->execute();
				}
				if ( $opts->isEnabledTrackLoginInvalid() ) {
					( new IPs\BotTrack\TrackLoginInvalid() )
						->setMod( $mod )
						->execute();
				}
				if ( $opts->isEnabledTrackFakeWebCrawler() ) {
					( new IPs\BotTrack\TrackFakeWebCrawler() )
						->setMod( $mod )
						->execute();
				}
				if ( $opts->isEnabledTrackInvalidScript() ) {
					( new IPs\BotTrack\TrackInvalidScriptLoad() )
						->setMod( $mod )
						->execute();
				}
			}

			/** Always run link cheese regardless of the verified bot or not */
			if ( $opts->isEnabledTrackLinkCheese() && $mod->canLinkCheese() ) {
				( new IPs\BotTrack\TrackLinkCheese() )
					->setMod( $mod )
					->execute();
			}
		}

		// Capture when admins un/mark comments as spam
		( new IPs\BotTrack\TrackCommentSpam() )
			->setMod( $mod )
			->execute();
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