<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Bots;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Signals\NotBotHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\SilentCaptchaComplexity;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Services;

class NotBotSignalStateIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const PUBLIC_TEST_IP = '93.184.216.44';

	private array $requestSnapshot = [];

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [ 'silentcaptcha_complexity' ] );
		$this->requireDb( 'ips' );
		$this->requireDb( 'bot_signals' );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	/**
	 * @dataProvider signalStateProvider
	 *
	 * @param array<string,string>|null $signalAges
	 * @param list<string>             $expectedRequired
	 */
	public function testRequiredAndNonRequiredSignalsMatchBotSignalState(
		?array $signalAges,
		string $complexity,
		array $expectedRequired
	) :void {
		$this->applyPublicRequestContext( self::PUBLIC_TEST_IP );
		$this->requireController()->opts->optSet( 'silentcaptcha_complexity', $complexity );
		if ( $signalAges !== null ) {
			TestDataFactory::insertBotSignal( self::PUBLIC_TEST_IP, $this->timestampsForSignalAges( $signalAges ) );
		}

		$handler = new NotBotHandler();

		$this->assertSameSignalSet( $expectedRequired, $handler->getRequiredSignals() );
		$this->assertSameSignalSet(
			\array_values( \array_diff(
				[ NotBotHandler::SIGNAL_NOTBOT, NotBotHandler::SIGNAL_ALTCHA ],
				$expectedRequired
			) ),
			$handler->getNonRequiredSignals()
		);
	}

	public function signalStateProvider() :array {
		return [
			'no record' => [
				null,
				SilentCaptchaComplexity::LOW,
				[ NotBotHandler::SIGNAL_NOTBOT, NotBotHandler::SIGNAL_ALTCHA ],
			],
			'both fresh' => [
				[
					'notbot_at' => 'fresh',
					'altcha_at' => 'fresh',
				],
				SilentCaptchaComplexity::LOW,
				[],
			],
			'both stale' => [
				[
					'notbot_at' => 'stale',
					'altcha_at' => 'stale',
				],
				SilentCaptchaComplexity::LOW,
				[ NotBotHandler::SIGNAL_NOTBOT, NotBotHandler::SIGNAL_ALTCHA ],
			],
			'only notbot stale' => [
				[
					'notbot_at' => 'stale',
					'altcha_at' => 'fresh',
				],
				SilentCaptchaComplexity::LOW,
				[ NotBotHandler::SIGNAL_NOTBOT ],
			],
			'only altcha stale' => [
				[
					'notbot_at' => 'fresh',
					'altcha_at' => 'stale',
				],
				SilentCaptchaComplexity::LOW,
				[ NotBotHandler::SIGNAL_ALTCHA ],
			],
			'complexity none' => [
				[
					'notbot_at' => 'stale',
					'altcha_at' => 'stale',
				],
				SilentCaptchaComplexity::NONE,
				[],
			],
		];
	}

	private function applyPublicRequestContext( string $ip ) :void {
		$this->applyCurrentRequestState(
			[
				'REMOTE_ADDR'    => $ip,
				'REQUEST_METHOD' => 'GET',
				'REQUEST_URI'    => '/',
			],
			[],
			[],
			[
				'ip'                => $ip,
				'ip_is_public'      => true,
				'is_security_admin' => false,
				'path'              => '/',
				'wp_is_ajax'        => false,
			]
		);
	}

	/**
	 * @param array<string,string> $signalAges
	 * @return array<string,int>
	 */
	private function timestampsForSignalAges( array $signalAges ) :array {
		$now = Services::Request()->ts();
		$timestamps = [];
		foreach ( $signalAges as $field => $age ) {
			$timestamps[ $field ] = $age === 'stale'
				? $now - HOUR_IN_SECONDS - 30
				: $now - 30;
		}
		return $timestamps;
	}

	/**
	 * @param list<string> $expected
	 * @param string[]     $actual
	 */
	private function assertSameSignalSet( array $expected, array $actual ) :void {
		$expected = \array_values( $expected );
		$actual = \array_values( $actual );
		\sort( $expected );
		\sort( $actual );

		$this->assertSame( $expected, $actual );
	}
}
