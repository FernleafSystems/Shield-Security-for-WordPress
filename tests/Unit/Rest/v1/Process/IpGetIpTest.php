<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\IpGetIp;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class IpGetIpTest extends BaseUnitTest {

	public function test_not_bot_info_preserves_legacy_keys_and_exposes_available_shieldnet_score() :void {
		$payload = $this->invokeNotBotInfo( $this->process( true, 42 ) );

		$this->assertSame( 73, $payload[ 'human_probability' ] );
		$this->assertSame( -12, $payload[ 'score_local' ] );
		$this->assertSame( 42, $payload[ 'score_shieldnet' ] );
		$this->assertTrue( $payload[ 'score_shieldnet_available' ] );
		$this->assertSame( 42, $payload[ 'score_shieldnet_value' ] );
		$this->assertSame( [ 'Known' => 'N/A' ], $payload[ 'signals' ] );
	}

	public function test_not_bot_info_preserves_legacy_unavailable_score_and_exposes_availability() :void {
		$payload = $this->invokeNotBotInfo( $this->process( false, null ) );

		$this->assertSame( '-', $payload[ 'score_shieldnet' ] );
		$this->assertFalse( $payload[ 'score_shieldnet_available' ] );
		$this->assertNull( $payload[ 'score_shieldnet_value' ] );
	}

	private function invokeNotBotInfo( IpGetIp $process ) :array {
		$method = new \ReflectionMethod( IpGetIp::class, 'getNotBotInfo' );
		$method->setAccessible( true );
		return $method->invoke( $process );
	}

	private function process( bool $shieldNetAvailable, ?int $shieldNetScore ) :IpGetIp {
		return new class( $shieldNetAvailable, $shieldNetScore ) extends IpGetIp {
			private bool $shieldNetAvailable;

			private ?int $shieldNetScore;

			public function __construct( bool $shieldNetAvailable, ?int $shieldNetScore ) {
				$this->shieldNetAvailable = $shieldNetAvailable;
				$this->shieldNetScore = $shieldNetScore;
			}

			protected function buildIpAnalysisData() :array {
				return [
					'ip'                   => '198.51.100.44',
					'ip_rules'             => [
						'offenses'   => 0,
						'is_blocked' => false,
						'is_bypass'  => false,
						'block_type' => '',
					],
					'identity'             => [
						'key'  => 'unknown',
						'name' => 'Unknown',
					],
					'geo'                  => [
						'country_code' => '',
					],
					'rdns'                 => [
						'is_available' => false,
						'hostname'     => '',
					],
					'shieldnet_reputation' => [
						'is_available' => $this->shieldNetAvailable,
						'score'        => $this->shieldNetScore,
					],
					'bot'                  => [
						'is_available'       => true,
						'is_bot'             => true,
						'human_probability'  => 73,
						'local_score'        => -12,
						'bot_risk_score'     => 27,
						'human_threshold'    => 45,
						'bot_risk_threshold' => 55,
						'is_high_bot_risk'   => false,
					],
					'signals'              => [
						'total'          => 1,
						'positive_total' => 100,
						'negative_total' => 0,
						'positive_rows'  => [
							[
								'key'   => 'known',
								'name'  => 'Known',
								'when'  => 'N/A',
								'score' => 100,
							],
						],
						'negative_rows'  => [],
						'rest_map'       => [
							'Known' => 'N/A',
						],
					],
				];
			}
		};
	}
}
