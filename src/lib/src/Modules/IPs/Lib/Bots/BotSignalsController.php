<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	BotTrack,
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Services\Services;

class BotSignalsController extends ExecOnceModConsumer {

	/**
	 * @var NotBot\NotBotHandler
	 */
	private $handlerNotBot;

	/**
	 * @var BotEventListener
	 */
	private $eventListener;

	protected function canRun() :bool {
		return $this->getCon()->this_req->ip_is_public;
	}

	protected function run() {
		$this->getEventListener()->execute();
		add_action( 'init', function () {
			foreach ( $this->enumerateBotTrackers() as $botTrackerClass ) {
				( new $botTrackerClass() )->setMod( $this->getMod() )->execute();
			}
		} );
		$this->getHandlerNotBot()->execute();
	}

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
						'audit_params' => [
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
			$this->handlerNotBot = ( new NotBot\NotBotHandler( true ) )
				->setMod( $this->getMod() );
		}
		return $this->handlerNotBot;
	}

	public function getEventListener() :BotEventListener {
		if ( !isset( $this->eventListener ) ) {
			$this->eventListener = ( new BotEventListener() )->setMod( $this->getMod() );
		}
		return $this->eventListener;
	}

	/**
	 * @return string[]
	 */
	private function enumerateBotTrackers() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		$trackers = [
			BotTrack\TrackCommentSpam::class
		];

		if ( !Services::WpUsers()->isUserLoggedIn() ) {

			if ( !$this->getCon()->this_req->request_bypasses_all_restrictions ) {
				if ( $opts->isEnabledTrackLoginFailed() ) {
					$trackers[] = BotTrack\TrackLoginFailed::class;
				}
				if ( $opts->isEnabledTrackLoginInvalid() ) {
					$trackers[] = BotTrack\TrackLoginInvalid::class;
				}
			}

			if ( $opts->isEnabledTrackLinkCheese() && $mod->canLinkCheese() ) {
				$trackers[] = BotTrack\TrackLinkCheese::class;
			}
		}

		return $trackers;
	}
}