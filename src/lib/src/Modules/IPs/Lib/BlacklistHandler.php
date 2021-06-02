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

		add_action( 'init', [ $this, 'loadBotDetectors' ] );

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
		foreach ( $this->enumerateBotTrackers() as $botTracker ) {
			$botTracker->setMod( $this->getMod() )->execute();
		}
	}

	/**
	 * @return IPs\BotTrack\Base[]
	 */
	private function enumerateBotTrackers() :array {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		$trackers = [
			new IPs\BotTrack\TrackCommentSpam()
		];

		if ( !Services::WpUsers()->isUserLoggedIn() ) {

			if ( !$mod->isTrustedVerifiedBot() ) {

				if ( $opts->isEnabledTrack404() ) {
					$trackers[] = new IPs\BotTrack\Track404();
				}
				if ( $opts->isEnabledTrackXmlRpc() ) {
					$trackers[] = new IPs\BotTrack\TrackXmlRpc();
				}
				if ( $opts->isEnabledTrackLoginFailed() ) {
					$trackers[] = new IPs\BotTrack\TrackLoginFailed();
				}
				if ( $opts->isEnabledTrackLoginInvalid() ) {
					$trackers[] = new IPs\BotTrack\TrackLoginInvalid();
				}
				if ( $opts->isEnabledTrackFakeWebCrawler() ) {
					$trackers[] = new IPs\BotTrack\TrackFakeWebCrawler();
				}
				if ( $opts->isEnabledTrackInvalidScript() ) {
					$trackers[] = new IPs\BotTrack\TrackInvalidScriptLoad();
				}
			}

			if ( $opts->isEnabledTrackLinkCheese() && $mod->canLinkCheese() ) {
				$trackers[] = new IPs\BotTrack\TrackLinkCheese();
			}
		}

		return $trackers;
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