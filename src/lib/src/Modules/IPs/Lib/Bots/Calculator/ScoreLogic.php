<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Reputation\BotScoringLogic;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class ScoreLogic {

	use PluginControllerConsumer;

	private $rawLogic = [];

	public function getFieldScoreLogic( $field ) :array {
		return $this->getScoringLogic()[ $field ] ?? [];
	}

	/**
	 * Triggered on a daily cron from BotSignalsCon to force update, but if for whatever reason the cron isn't
	 * running, it'll be automatically updated as-required every 3 days using the fallback.
	 *
	 * Basically: if the cron isn't ever running to update the scoring logic, then fallback data is always used.
	 */
	public function getScoringLogic( bool $buildFromAPI = false ) :array {
		if ( empty( $this->rawLogic ) ) {
			$logic = Transient::Get( 'shield-bot-scoring-logic' );
			if ( empty( $logic ) && $buildFromAPI ) {
				$logicLoader = new BotScoringLogic();
				$logicLoader->shield_net_params_required = false;
				$logic = $logicLoader->retrieve();
				if ( !empty( $logic ) ) {
					Transient::Set( 'shield-bot-scoring-logic', $logic, \WEEK_IN_SECONDS );
				}
			}
			$this->rawLogic = empty( $logic ) ? $this->buildFallback() : $logic;
		}
		return $this->rawLogic;
	}

	/**
	 * @return array[]
	 * @note Copied from ShieldNET API
	 */
	protected function buildFallback() :array {
		return \array_map(
			function ( $score ) {
				return $score + [
						0  => 0,
						-1 => 0,
					];
			},
			\array_merge( $this->getPositiveSignals(), $this->getNegativeSignals() )
		);
	}

	protected function getPositiveSignals() :array {
		return [
			'created'     => [
				3  => 0,
				15 => 15,
			],
			'notbot'      => [
				0               => -10,
				HOUR_IN_SECONDS => 100,
				-1              => 75,
			],
			'altcha'      => [
				0               => -10,
				HOUR_IN_SECONDS => 150,
				-1              => 75,
			],
			'frontpage'   => [
				0                => -15,
				\HOUR_IN_SECONDS => 25,
				-1               => 15,
			],
			'loginpage'   => [
				-1 => 15,
			],
			'unmarkspam'  => [
				\WEEK_IN_SECONDS => 75,
				-1               => 35,
			],
			'captchapass' => [
				\DAY_IN_SECONDS => 55,
				-1              => 25,
			],
			'auth'        => [
				\DAY_IN_SECONDS => 175,
				-1              => 150,
			],
			'unblocked'   => [
				\DAY_IN_SECONDS => 100,
				-1              => 75,
			],
			'bypass'      => [
				-1 => 150,
			],
		];
	}

	protected function getNegativeSignals() :array {
		return [
			'bt404'           => [
				\HOUR_IN_SECONDS => -15,
				-1               => -5,
			],
			'btfake'          => [
				\DAY_IN_SECONDS => -75,
				-1              => -45,
			],
			'btcheese'        => [
				\DAY_IN_SECONDS => -65,
				-1              => -45,
			],
			'btloginfail'     => [
				\MINUTE_IN_SECONDS => -75,
				-1                 => -45,
			],
			'btua'            => [
				\DAY_IN_SECONDS => -35,
				-1              => -25,
			],
			'btxml'           => [
				\DAY_IN_SECONDS => -55,
				-1              => -35,
			],
			'btlogininvalid'  => [
				\HOUR_IN_SECONDS => -85,
				-1               => -55,
			],
			'btinvalidscript' => [
				\HOUR_IN_SECONDS => -25,
				-1               => -15,
			],
			'cooldown'        => [
				\MINUTE_IN_SECONDS => -25,
				-1                 => -15,
			],
			'humanspam'       => [
				\DAY_IN_SECONDS => -30,
				-1              => -15,
			],
			'markspam'        => [
				\WEEK_IN_SECONDS => -50,
				-1               => -25,
			],
			'captchafail'     => [
				\MINUTE_IN_SECONDS => -55,
				-1                 => -25,
			],
			'firewall'        => [
				\DAY_IN_SECONDS => -35,
				-1              => -15,
			],
			'ratelimit'       => [
				\MINUTE_IN_SECONDS => -55,
				-1                 => -25,
			],
			'offense'         => [
				\MINUTE_IN_SECONDS => -35,
				-1                 => -25,
			],
			'blocked'         => [
				\DAY_IN_SECONDS => -55,
				-1              => -45,
			],
		];
	}
}