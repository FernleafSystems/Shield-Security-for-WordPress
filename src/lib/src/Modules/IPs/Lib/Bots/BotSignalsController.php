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
		return self::con()->this_req->ip_is_public || Services::Request()->query( 'force_notbot' );
	}

	protected function run() {
		$this->getEventListener()->execute();
		add_action( 'init', function () {
			foreach ( $this->enumerateBotTrackers() as $botTrackerClass ) {
				( new $botTrackerClass() )->execute();
			}
		} );
		$this->getHandlerNotBot()->execute();
		$this->registerFrontPageLoad();
		$this->registerLoginPageLoad();
	}

	public function isBot( string $IP = '', bool $allowEventFire = true, bool $forceCheck = false ) :bool {

		if ( !isset( $this->isBots[ $IP ] ) || $forceCheck ) {
			$con = self::con();

			$this->isBots[ $IP ] = false;

			if ( !$con->comps->opts_lookup->enabledAntiBotEngine() ) {
				$con->fireEvent( 'ade_check_option_disabled' );
			}
			elseif ( !$this->mod()->isModOptEnabled() ) {
				$con->fireEvent( 'ade_check_module_disabled' );
			}
			else {
				$botScoreMinimum = $con->comps->opts_lookup->getAntiBotMinScore();
				if ( $botScoreMinimum > 0 ) {

					$score = ( new Calculator\CalculateVisitorBotScores() )
						->setIP( empty( $IP ) ? self::con()->this_req->ip : $IP )
						->probability();

					$this->isBots[ $IP ] = $score < $botScoreMinimum;

					if ( $allowEventFire ) {
						$con->fireEvent(
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
		return isset( self::con()->comps ) ? self::con()->comps->not_bot :
			( $this->handlerNotBot ?? $this->handlerNotBot = new NotBotHandler() );
	}

	public function getEventListener() :BotEventListener {
		return $this->eventListener ?? $this->eventListener = new BotEventListener();
	}

	/**
	 * @return string[]
	 */
	private function enumerateBotTrackers() :array {
		$con = self::con();

		$trackers = [
			BotTrack\TrackCommentSpam::class
		];

		if ( !Services::WpUsers()->isUserLoggedIn() ) {
			if ( !$con->this_req->request_bypasses_all_restrictions ) {
				if ( !$con->opts->optIs( 'track_loginfailed', 'disabled' ) ) {
					$trackers[] = BotTrack\TrackLoginFailed::class;
				}
				if ( !$con->opts->optIs( 'track_logininvalid', 'disabled' ) ) {
					$trackers[] = BotTrack\TrackLoginInvalid::class;
				}
			}
		}

		if ( !$con->opts->optIs( 'track_linkcheese', 'disabled' ) ) {
			$trackers[] = BotTrack\TrackLinkCheese::class;
		}

		return $trackers;
	}

	private function registerFrontPageLoad() {
		add_action( self::con()->prefix( 'pre_plugin_shutdown' ), function () {
			if ( Services::Request()->isGet() && did_action( 'wp' )
				 && ( is_page() || is_single() || is_front_page() || is_home() ) ) {
				$this->getEventListener()->fireEventForIP( self::con()->this_req->ip, 'frontpage_load' );
			}
		} );
	}

	private function registerLoginPageLoad() {
		add_action( 'login_footer', function () {
			$req = Services::Request();
			if ( $req->isGet() ) {
				$this->getEventListener()->fireEventForIP( self::con()->this_req->ip, 'loginpage_load' );
			}
		} );
	}
}