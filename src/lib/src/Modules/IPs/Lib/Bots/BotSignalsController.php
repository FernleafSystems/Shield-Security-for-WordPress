<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	BotTrack,
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\SendIPReputation;
use FernleafSystems\Wordpress\Services\Services;

class BotSignalsController extends ExecOnceModConsumer {

	use PluginCronsConsumer;

	/**
	 * @var NotBot\NotBotHandler
	 */
	private $handlerNotBot;

	/**
	 * @var BotEventListener
	 */
	private $eventListener;

	public function isBot( string $IP = '', bool $allowEventFire = true ) :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$isBot = false;
		$botScoreMinimum = (int)apply_filters( 'shield/antibot_score_minimum', $opts->getAntiBotMinimum() );

		if ( $botScoreMinimum > 0 ) {

			$score = ( new Calculator\CalculateVisitorBotScores() )
				->setMod( $this->getMod() )
				->setIP( empty( $IP ) ? Services::IP()->getRequestIp() : $IP )
				->probability();

			$isBot = $score < $botScoreMinimum;

			if ( $allowEventFire ) {
				$this->getCon()->fireEvent(
					'antibot_'.( $isBot ? 'fail' : 'pass' ),
					[
						'audit' => [
							'score'   => $score,
							'minimum' => $botScoreMinimum,
						]
					]
				);
			}
		}
		return $isBot;
	}

	public function getHandlerNotBot() :NotBot\NotBotHandler {
		if ( !isset( $this->handlerNotBot ) ) {
			$this->handlerNotBot = ( new NotBot\NotBotHandler() )->setMod( $this->getMod() );
		}
		return $this->handlerNotBot;
	}

	public function getEventListener() :BotEventListener {
		if ( !isset( $this->eventListener ) ) {
			$this->eventListener = ( new BotEventListener() )->setMod( $this->getMod() );
		}
		return $this->eventListener;
	}

	protected function run() {
		$this->getEventListener()->execute();
		add_action( 'init', function () {
			foreach ( $this->enumerateBotTrackers() as $botTracker ) {
				$botTracker->setMod( $this->getMod() )->execute();
			}
			$this->getHandlerNotBot()->execute();
		} );
		$this->setupCronHooks();
	}

	/**
	 * @return BotTrack\Base[]
	 */
	private function enumerateBotTrackers() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$trackers = [
			new BotTrack\TrackCommentSpam()
		];

		if ( !Services::WpUsers()->isUserLoggedIn() ) {

			if ( !$mod->isTrustedVerifiedBot() ) {

				if ( $opts->isEnabledTrack404() ) {
					$trackers[] = new BotTrack\Track404();
				}
				if ( $opts->isEnabledTrackXmlRpc() ) {
					$trackers[] = new BotTrack\TrackXmlRpc();
				}
				if ( $opts->isEnabledTrackLoginFailed() ) {
					$trackers[] = new BotTrack\TrackLoginFailed();
				}
				if ( $opts->isEnabledTrackLoginInvalid() ) {
					$trackers[] = new BotTrack\TrackLoginInvalid();
				}
				if ( $opts->isEnabledTrackFakeWebCrawler() ) {
					$trackers[] = new BotTrack\TrackFakeWebCrawler();
				}
				if ( $opts->isEnabledTrackInvalidScript() ) {
					$trackers[] = new BotTrack\TrackInvalidScriptLoad();
				}
			}

			if ( $opts->isEnabledTrackLinkCheese() && $mod->canLinkCheese() ) {
				$trackers[] = new BotTrack\TrackLinkCheese();
			}
		}

		return $trackers;
	}

	public function runDailyCron() {
		$con = $this->getCon();
		if ( is_main_network() && $con->isPremiumActive()
			 && $con->getModule_Plugin()->getShieldNetApiController()->canHandshake() ) {
			$data = ( new ShieldNET\BuildData() )
				->setMod( $this->getCon()->getModule_IPs() )
				->build();
			if ( !empty( $data ) ) {
				( new SendIPReputation() )
					->setMod( $this->getMod() )
					->send( $data );
			}
		}
	}
}