<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\Calculator\CalculateVisitorBotScores;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Tests bot score calculation: positive signals raise the score toward
 * "human", negative signals lower it toward "bot".
 */
class BotScoreCalculationTest extends ShieldIntegrationTestCase {

	private function calcScores( string $ip ) :CalculateVisitorBotScores {
		$calc = new CalculateVisitorBotScores();
		$calc->setIP( $ip );
		return $calc;
	}

	public function test_clean_visitor_scores_higher_than_suspicious() {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();

		$cleanIp = '192.0.2.200';
		TestDataFactory::insertBotSignal( $cleanIp, [
			'notbot_at' => $now - 30,
			'auth_at'   => $now - 60,
		] );

		$suspiciousIp = '192.0.2.201';
		TestDataFactory::insertBotSignal( $suspiciousIp, [
			'bt404_at'       => $now - 10,
			'btfake_at'      => $now - 20,
			'btloginfail_at' => $now - 30,
		] );

		$cleanProb = $this->calcScores( $cleanIp )->probability();
		$suspiciousProb = $this->calcScores( $suspiciousIp )->probability();

		$this->assertGreaterThan( $suspiciousProb, $cleanProb,
			'Clean visitor (positive signals) should score higher than suspicious visitor (negative signals)' );
	}

	public function test_no_signals_gives_baseline_score() {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$ip = '192.0.2.202';

		// No bot signal record inserted at all
		$calc = $this->calcScores( $ip );
		$scores = $calc->scores();
		$probability = $calc->probability();

		$this->assertIsArray( $scores );
		$this->assertGreaterThanOrEqual( 0, $probability );
		$this->assertLessThanOrEqual( 100, $probability );
	}

	public function test_probability_is_clamped_between_0_and_100() {
		$this->requireDb( 'bot_signals' );
		$this->requireDb( 'ips' );

		$now = Services::Request()->ts();

		// Create extreme negative signals
		$ip = '192.0.2.203';
		TestDataFactory::insertBotSignal( $ip, [
			'bt404_at'       => $now - 1,
			'btfake_at'      => $now - 1,
			'btloginfail_at' => $now - 1,
			'btxml_at'       => $now - 1,
			'offense_at'     => $now - 1,
			'blocked_at'     => $now - 1,
		] );

		$probability = $this->calcScores( $ip )->probability();

		$this->assertGreaterThanOrEqual( 0, $probability, 'Probability should never go below 0' );
		$this->assertLessThanOrEqual( 100, $probability, 'Probability should never exceed 100' );
	}
}
