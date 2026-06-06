<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse\GeneralViewDataBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class IpAnalyseGeneralViewDataBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'rawurlencode_deep' )->alias( static fn( $value ) => \is_array( $value ) ? \array_map( 'rawurlencode', $value ) : \rawurlencode( (string)$value ) );
		Functions\when( 'add_query_arg' )->alias(
			static fn( array $params, string $url ) :string => $url.'?'.\http_build_query( $params )
		);
		$this->installController();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_emits_required_twig_contract_for_available_data() :void {
		$data = $this->builder( true )->build( '198.51.100.10' );

		$this->assertArrayHasKey( 'hrefs', $data );
		$this->assertArrayHasKey( 'strings', $data );
		$this->assertArrayHasKey( 'vars', $data );
		$this->assertSame( '198.51.100.10', $data[ 'vars' ][ 'ip' ] );
		$this->assertSame( '72', $data[ 'vars' ][ 'status' ][ 'snapi_reputation_score' ] );
		$this->assertSame( 'crawler.example.test', $data[ 'vars' ][ 'identity' ][ 'rdns' ] );
		$this->assertSame( '+100', $data[ 'vars' ][ 'signals' ][ 'positive' ][ 'rows' ][ 0 ][ 'score_label' ] );
		$this->assertSame( 'success', $data[ 'vars' ][ 'overview' ][ 'verdict_bar' ][ 'fill_class' ] );
	}

	public function test_build_owns_unavailable_display_values() :void {
		$data = $this->builder( false )->build( '198.51.100.20' );

		$this->assertSame( 'Data not available', $data[ 'vars' ][ 'status' ][ 'snapi_reputation_score' ] );
		$this->assertSame( 'Data not available', $data[ 'vars' ][ 'identity' ][ 'rdns' ] );
		$this->assertSame( 'secondary', $data[ 'vars' ][ 'overview' ][ 'shieldnet_reputation_badge_class' ] );
		$this->assertSame( 'Unknown', $data[ 'vars' ][ 'signals' ][ 'negative' ][ 'rows' ][ 0 ][ 'name' ] );
	}

	private function builder( bool $available ) :GeneralViewDataBuilder {
		return new class( $available ) extends GeneralViewDataBuilder {
			private bool $available;

			public function __construct( bool $available ) {
				$this->available = $available;
			}

			protected function buildAnalysisData( string $ip ) :array {
				return [
					'ip'                   => $ip,
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
						'country_code' => $this->available ? 'US' : '',
					],
					'rdns'                 => [
						'is_available' => $this->available,
						'hostname'     => $this->available ? 'crawler.example.test' : '',
					],
					'shieldnet_reputation' => [
						'is_available' => $this->available,
						'score'        => $this->available ? 72 : null,
					],
					'bot'                  => [
						'is_available'       => true,
						'is_bot'             => false,
						'human_probability'  => 82,
						'local_score'        => 100,
						'bot_risk_score'     => 18,
						'human_threshold'    => 45,
						'bot_risk_threshold' => 55,
						'is_high_bot_risk'   => false,
					],
					'signals'              => [
						'total'          => 2,
						'positive_total' => 100,
						'negative_total' => -25,
						'positive_rows'  => [
							[
								'key'   => 'known',
								'name'  => 'Known',
								'when'  => 'N/A',
								'score' => 100,
							],
						],
						'negative_rows'  => [
							[
								'key'   => 'brandnew',
								'name'  => 'Unknown',
								'when'  => 'Never Recorded',
								'score' => -25,
							],
						],
						'rest_map'       => [
							'Unknown' => 'Never Recorded',
						],
					],
				];
			}
		};
	}

	private function installController() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->labels = new class {
			public function getBrandName( string $brand ) :string {
				return $brand === 'shieldnet' ? 'ShieldNET' : $brand;
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}
