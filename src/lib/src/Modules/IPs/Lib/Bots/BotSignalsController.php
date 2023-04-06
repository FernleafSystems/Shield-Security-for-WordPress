<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	BotTrack,
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\NotBotHandler;
use FernleafSystems\Wordpress\Services\Services;

class BotSignalsController extends ExecOnceModConsumer {

	/**
	 * @var NotBotHandler
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
		$this->registerFrontPageLoad();
		$this->registerLoginPageLoad();
	}

	public function isBot( string $IP = '', bool $allowEventFire = true ) :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$isBot = false;
		$botScoreMinimum = (int)apply_filters( 'shield/antibot_score_minimum', $opts->getAntiBotMinimum() );

		if ( $botScoreMinimum > 0 ) {

			$score = ( new Calculator\CalculateVisitorBotScores() )
				->setIP( empty( $IP ) ? $this->getCon()->this_req->ip : $IP )
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
		return $this->handlerNotBot ?? $this->handlerNotBot = ( new NotBotHandler() )->setMod( $this->getMod() );
	}

	public function getEventListener() :BotEventListener {
		return $this->eventListener ?? $this->eventListener = ( new BotEventListener() )->setMod( $this->getMod() );
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

	private function registerFrontPageLoad() {
		add_action( $this->getCon()->prefix( 'pre_plugin_shutdown' ), function () {
			if ( Services::Request()->isGet() && did_action( 'wp' )
				 && ( is_page() || is_single() || is_front_page() || is_home() ) ) {
				$this->getEventListener()->fireEventForIP( $this->getCon()->this_req->ip, 'frontpage_load' );
			}
		} );
	}

	private function registerLoginPageLoad() {
		add_action( 'login_footer', function () {
			$req = Services::Request();
			if ( $req->isGet() ) {
				$this->getEventListener()->fireEventForIP( $this->getCon()->this_req->ip, 'loginpage_load' );
			}
		} );
	}
}