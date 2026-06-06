<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\IPs\Lib\IpAnalysis;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\BotSignalRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpAnalysis\IpAnalysisDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class IpAnalysisDataBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installController();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_returns_complete_available_contract() :void {
		$data = $this->builder( [
			'country' => 'US',
			'rdns'    => [
				'is_available' => true,
				'hostname'     => 'crawler.example.test',
			],
			'snapi'   => [
				'is_available' => true,
				'score'        => 72,
			],
			'scores'  => [
				'known' => 100,
			],
		] )->build( '198.51.100.10' );

		$this->assertSame( '198.51.100.10', $data[ 'ip' ] );
		$this->assertSame( 4, $data[ 'ip_rules' ][ 'offenses' ] );
		$this->assertSame( 'US', $data[ 'geo' ][ 'country_code' ] );
		$this->assertTrue( $data[ 'rdns' ][ 'is_available' ] );
		$this->assertSame( 'crawler.example.test', $data[ 'rdns' ][ 'hostname' ] );
		$this->assertTrue( $data[ 'shieldnet_reputation' ][ 'is_available' ] );
		$this->assertSame( 72, $data[ 'shieldnet_reputation' ][ 'score' ] );
		$this->assertTrue( $data[ 'bot' ][ 'is_available' ] );
		$this->assertSame( 82, $data[ 'bot' ][ 'human_probability' ] );
		$this->assertCount( 1, $data[ 'signals' ][ 'positive_rows' ] );
		$this->assertSame( 100, $data[ 'signals' ][ 'positive_total' ] );
	}

	public function test_build_returns_explicit_unavailable_contract_and_unknown_signal_fallback() :void {
		$data = $this->builder( [
			'country' => '',
			'rdns'    => [
				'is_available' => false,
				'hostname'     => '',
			],
			'snapi'   => [
				'is_available' => false,
				'score'        => null,
			],
			'scores'  => [
				'brandnew' => -25,
				'mystery'  => -30,
				'known'    => 100,
			],
		] )->build( '198.51.100.20' );

		$this->assertSame( '', $data[ 'geo' ][ 'country_code' ] );
		$this->assertFalse( $data[ 'rdns' ][ 'is_available' ] );
		$this->assertFalse( $data[ 'shieldnet_reputation' ][ 'is_available' ] );
		$this->assertNull( $data[ 'shieldnet_reputation' ][ 'score' ] );
		$this->assertSame( 'Unknown', $data[ 'signals' ][ 'negative_rows' ][ 0 ][ 'name' ] );
		$this->assertSame( -25, $data[ 'signals' ][ 'negative_rows' ][ 0 ][ 'score' ] );
		$this->assertArrayHasKey( 'Unknown', $data[ 'signals' ][ 'rest_map' ] );
		$this->assertArrayHasKey( 'Unknown (mystery)', $data[ 'signals' ][ 'rest_map' ] );
		$this->assertSame( 3, $data[ 'signals' ][ 'total' ] );
	}

	private function builder( array $contract ) :IpAnalysisDataBuilder {
		return new class( $contract ) extends IpAnalysisDataBuilder {
			private array $contract;

			public function __construct( array $contract ) {
				$this->contract = $contract;
			}

			protected function buildIpRules( string $ip ) :IpRuleStatus {
				return new class( $ip ) extends IpRuleStatus {
					public function getOffenses() :int {
						return 4;
					}

					public function isBlocked() :bool {
						return false;
					}

					public function isBypass() :bool {
						return false;
					}

					public function getBlockType() :string {
						return '';
					}
				};
			}

			protected function buildIdentity( string $ip, IpRuleStatus $rules ) :array {
				unset( $ip, $rules );
				return [
					'key'  => 'unknown',
					'name' => 'Unknown',
				];
			}

			protected function lookupCountryCode( string $ip ) :string {
				unset( $ip );
				return (string)$this->contract[ 'country' ];
			}

			protected function buildRdns( string $ip ) :array {
				unset( $ip );
				return $this->contract[ 'rdns' ];
			}

			protected function buildShieldNetReputation( string $ip ) :array {
				unset( $ip );
				return $this->contract[ 'snapi' ];
			}

			protected function buildBotAnalysis( string $ip ) :array {
				unset( $ip );
				$scores = $this->contract[ 'scores' ];
				return [
					'is_available'       => true,
					'is_bot'             => false,
					'human_probability'  => 82,
					'local_score'        => \array_sum( $scores ),
					'bot_risk_score'     => 18,
					'human_threshold'    => 45,
					'bot_risk_threshold' => 55,
					'is_high_bot_risk'   => false,
					'scores'             => $scores,
				];
			}

			protected function loadSignalRecord( string $ip ) :?BotSignalRecord {
				unset( $ip );
				return null;
			}
		};
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->labels = new class {
			public function getBrandName( string $brand ) :string {
				return $brand === 'silentcaptcha' ? 'silentCAPTCHA' : $brand;
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}
