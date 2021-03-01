<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

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

	public function isBot( string $IP = '' ) :bool {
		$score = ( new CalculateVisitorBotScores() )
			->setMod( $this->getMod() )
			->setIP( empty( $IP ) ? Services::IP()->getRequestIp() : $IP )
			->probability();
		$botScoreMinimum = (int)apply_filters( 'shield/antibot_score_minimum',
			(int)$this->getOptions()->getOpt( 'antibot_minimum', 50 ) );

		$isBot = $score < $botScoreMinimum;

		$this->getCon()->fireEvent(
			'antibot_'.( $isBot ? 'fail' : 'pass' ),
			[
				'audit' => [
					'score'   => $score,
					'minimum' => $botScoreMinimum,
				]
			]
		);
		return $isBot;
	}

	public function verifyNotBot( string $IP = '' ) :bool {
		return !$this->isBot( $IP );
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