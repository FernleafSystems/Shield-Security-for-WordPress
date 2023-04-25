<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	BotTrack,
	ModConsumer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\NotBotHandler;
use FernleafSystems\Wordpress\Services\Services;

class BotSignalsController {

	use ExecOnce;
	use ModConsumer;

	/**
	 * @var NotBotHandler
	 */
	private $handlerNotBot;

	/**
	 * @var BotEventListener
	 */
	private $eventListener;

	private $isBots = [];

	protected function canRun() :bool {
		return $this->con()->this_req->ip_is_public;
	}

	protected function run() {
		$this->getEventListener()->execute();
		add_action( 'init', function () {
			foreach ( $this->enumerateBotTrackers() as $botTrackerClass ) {
				( new $botTrackerClass() )->setMod( $this->mod() )->execute();
			}
		} );
		$this->getHandlerNotBot()->execute();
		$this->registerFrontPageLoad();
		$this->registerLoginPageLoad();
	}

	public function isBot( string $IP = '', bool $allowEventFire = true, bool $forceCheck = false ) :bool {

		if ( !isset( $this->isBots[ $IP ] ) || $forceCheck ) {

			$this->isBots[ $IP ] = false;

			$opts = \method_exists( $this, 'opts' ) ? $this->opts() : $this->getOptions();

			if ( !$opts->isEnabledAntiBotEngine() ) {
				$this->con()->fireEvent( 'ade_check_option_disabled' );
			}
			elseif ( !$this->mod()->isModOptEnabled() ) {
				$this->con()->fireEvent( 'ade_check_module_disabled' );
			}
			else {
				$botScoreMinimum = (int)apply_filters( 'shield/antibot_score_minimum', $opts->getAntiBotMinimum() );

				if ( $botScoreMinimum > 0 ) {

					$score = ( new Calculator\CalculateVisitorBotScores() )
						->setIP( empty( $IP ) ? $this->getCon()->this_req->ip : $IP )
						->probability();

					$this->isBots[ $IP ] = $score < $botScoreMinimum;

					if ( $allowEventFire ) {
						$this->getCon()->fireEvent(
							'antibot_'.( $this->isBots[ $IP ] ? 'fail' : 'pass' ),
							[
								'audit_params' => [
									'score'   => $score,
									'minimum' => $botScoreMinimum,
								]
							]
						);
					}
				}
			}
		}

		return $this->isBots[ $IP ] ?? false;
	}

	public function getHandlerNotBot() :NotBot\NotBotHandler {
		return $this->handlerNotBot ?? $this->handlerNotBot = new NotBotHandler();
	}

	public function getEventListener() :BotEventListener {
		return $this->eventListener ?? $this->eventListener = new BotEventListener();
	}

	/**
	 * @return string[]
	 */
	private function enumerateBotTrackers() :array {

		$trackers = [
			BotTrack\TrackCommentSpam::class
		];

		if ( !Services::WpUsers()->isUserLoggedIn() ) {

			if ( !$this->getCon()->this_req->request_bypasses_all_restrictions ) {
				if ( $this->opts()->isEnabledTrackLoginFailed() ) {
					$trackers[] = BotTrack\TrackLoginFailed::class;
				}
				if ( $this->opts()->isEnabledTrackLoginInvalid() ) {
					$trackers[] = BotTrack\TrackLoginInvalid::class;
				}
			}

			if ( $this->opts()->isEnabledTrackLinkCheese() && $this->mod()->canLinkCheese() ) {
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