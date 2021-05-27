<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;

class BlacklistHandler extends Modules\Base\Common\ExecOnceModConsumer {

	use PluginCronsConsumer;

	protected function canRun() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return $opts->isEnabledAutoBlackList();
	}

	protected function run() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		if ( Services::WpGeneral()->isCron() && $this->getCon()->isPremiumActive() ) {
			$this->setupCronHooks();
		}

		( new IPs\Components\UnblockIpByFlag() )
			->setMod( $mod )
			->run();

		add_action( 'init', [ $this, 'loadBotDetectors' ] ); // hook in the bot detection

		if ( !$mod->isVisitorWhitelisted() && !$this->isRequestWhitelisted() ) {

			// We setup offenses processing immediately but run the blocks on 'init
			( new ProcessOffenses() )
				->setMod( $this->getMod() )
				->execute();

			add_action( 'init', function () {
				( new BlockRequest() )
					->setMod( $this->getMod() )
					->execute();
			}, -100000 );
		}
	}

	public function loadBotDetectors() {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		if ( !Services::WpUsers()->isUserLoggedIn() ) {

			if ( !$mod->isTrustedVerifiedBot() ) {
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

	private function isRequestWhitelisted() :bool {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		$isWhitelisted = false;
		$whitelistPaths = $opts->getRequestWhitelistAsRegex();
		if ( !empty( $whitelistPaths ) ) {
			$sPath = strtolower( '/'.ltrim( (string)Services::Request()->getPath(), '/' ) );
			foreach ( $whitelistPaths as $rule ) {
				if ( preg_match( $rule, $sPath ) ) {
					$isWhitelisted = true;
					break;
				}
			}
		}
		return $isWhitelisted;
	}

	public function runHourlyCron() {
		( new IPs\Components\ImportIpsFromFile() )
			->setMod( $this->getMod() )
			->execute();
	}
}