<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class BotSignalsController {

	use ModConsumer;
	use ExecOnce;

	/**
	 * @var NotBot\NotBotHandler
	 */
	private $handlerNotBot;

	/**
	 * @var EventListener
	 */
	private $eventListener;

	public function verifyNotBot() :bool {
		$score = \shield_get_bot_probability_score();
		$botScoreThreshold = (int)apply_filters( 'shield/antibot_score_threshold', 50 );
		$notBot = $score < $botScoreThreshold;

		$this->getCon()->fireEvent(
			'antibot_'.( $notBot ? 'pass' : 'fail' ),
			[
				'audit' => [
					'score'     => $score,
					'threshold' => $botScoreThreshold,
				]
			]
		);
		return $notBot;
	}

	public function getHandlerNotBot() :NotBot\NotBotHandler {
		if ( !isset( $this->handlerNotBot ) ) {
			$this->handlerNotBot = ( new NotBot\NotBotHandler() )->setMod( $this->getMod() );
		}
		return $this->handlerNotBot;
	}

	public function getEventListener() :EventListener {
		if ( !isset( $this->eventListener ) ) {
			$this->eventListener = ( new EventListener() )->setMod( $this->getMod() );
		}
		return $this->eventListener;
	}

	protected function run() {
		$this->getEventListener()->execute();
		add_action( 'init', function () {
			$this->getHandlerNotBot()->execute();
		} );
	}
}