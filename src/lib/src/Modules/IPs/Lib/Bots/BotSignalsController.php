<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Crons\PluginCronsConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\BotTrack;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\ScoreLogic;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\NotBotHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BotSignalsController {

	use ExecOnce;
	use PluginControllerConsumer;
	use PluginCronsConsumer;

	private BotEventListener $eventListener;

	private array $isBots = [];

	protected function run() {

		if ( self::con()->this_req->ip_is_public || Services::Request()->query( 'force_notbot' ) ) {
			$this->getEventListener()->execute();
			add_action( 'init', function () {
				foreach ( $this->enumerateBotTrackers() as $botTrackerClass ) {
					( new $botTrackerClass() )->execute();
				}
			} );
			self::con()->comps->not_bot->execute();
			$this->registerFrontPageLoad();
			$this->registerLoginPageLoad();
		}

		$this->setupCronHooks();
	}

	public function runDailyCron() {
		( new ScoreLogic() )->getScoringLogic( true );
	}

	public function isBot( string $IP = '', bool $allowEventFire = true, bool $forceCheck = false ) :bool {

		if ( !isset( $this->isBots[ $IP ] ) || $forceCheck ) {
			$con = self::con();

			$this->isBots[ $IP ] = false;

			if ( !$con->comps->opts_lookup->enabledAntiBotEngine() ) {
				$con->fireEvent( 'ade_check_option_disabled' );
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

	public function getAllowableExt404s() :array {
		$def = self::con()->cfg->configuration->def( 'bot_signals' )[ 'allowable_ext_404s' ] ?? [];
		return \array_unique( \array_filter(
			apply_filters( 'shield/bot_signals_allowable_extensions_404s', $def ),
			function ( $ext ) {
				return !empty( $ext ) && \is_string( $ext ) && \preg_match( '#^[a-z\d]+$#i', $ext );
			}
		) );
	}

	public function getAllowablePaths404s() :array {
		$def = self::con()->cfg->configuration->def( 'bot_signals' )[ 'allowable_paths_404s' ] ?? [];
		return \array_unique( \array_filter(
			apply_filters( 'shield/bot_signals_allowable_paths_404s', $def ),
			function ( $ext ) {
				return !empty( $ext ) && \is_string( $ext );
			}
		) );
	}

	public function getAllowableScripts() :array {
		$def = self::con()->cfg->configuration->def( 'bot_signals' )[ 'allowable_invalid_scripts' ] ?? [];
		return \array_unique( \array_filter(
			apply_filters( 'shield/bot_signals_allowable_invalid_scripts', $def ),
			function ( $script ) {
				return !empty( $script ) && \is_string( $script ) && \strpos( $script, '.php' );
			}
		) );
	}

	public function getEventListener() :BotEventListener {
		return $this->eventListener ??= new BotEventListener();
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
			$req = Services::Request();
			if ( $req->isGet() && did_action( 'wp' ) && ( is_page() || is_single() || is_front_page() || is_home() ) ) {
				try {
					$record = ( new BotSignalsRecord() )
						->setIP( self::con()->this_req->ip )
						->retrieve();
					if ( $req->ts() - $record->frontpage_at > MINUTE_IN_SECONDS*30 ) {
						$this->getEventListener()->fireEventForIP( self::con()->this_req->ip, 'frontpage_load' );
					}
				}
				catch ( \Exception $e ) {
				}
			}
		} );
	}

	private function registerLoginPageLoad() {
		add_action( 'login_footer', function () {
			$req = Services::Request();
			if ( $req->isGet() ) {
				try {
					$record = ( new BotSignalsRecord() )
						->setIP( self::con()->this_req->ip )
						->retrieve();
					if ( $req->ts() - $record->loginpage_at > MINUTE_IN_SECONDS*10 ) {
						$this->getEventListener()->fireEventForIP( self::con()->this_req->ip, 'loginpage_load' );
					}
				}
				catch ( \Exception $e ) {
				}
			}
		} );
	}
}