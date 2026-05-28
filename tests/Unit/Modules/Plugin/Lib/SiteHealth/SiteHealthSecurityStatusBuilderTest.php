<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\SiteHealth;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteHealth\SiteHealthSecurityStatusBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory,
	UnitTestPluginUrls
};

class SiteHealthSecurityStatusBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html' )->alias(
			static fn( string $text ) :string => \htmlspecialchars( $text, \ENT_QUOTES )
		);
		Functions\when( 'esc_url' )->alias( static fn( string $url ) :string => $url );

		UnitTestControllerFactory::install( new UnitTestPluginUrls() );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_tests_registers_stable_zone_callbacks() :void {
		$builder = $this->builder(
			[
				'secadmin' => 'Security Admin',
				'firewall' => 'Firewall',
			],
			[]
		);

		$tests = $builder->buildTests();

		$this->assertSame(
			[ 'shield_security_secadmin', 'shield_security_firewall' ],
			\array_keys( $tests )
		);
		$this->assertSame( 'Security Admin', $tests[ 'shield_security_secadmin' ][ 'label' ] );
		$this->assertTrue( $tests[ 'shield_security_secadmin' ][ 'skip_cron' ] );
		$this->assertIsCallable( $tests[ 'shield_security_secadmin' ][ 'test' ] );

		$result = \call_user_func( $tests[ 'shield_security_secadmin' ][ 'test' ] );

		$this->assertSame( 'shield_security_secadmin', $result[ 'test' ] );
		$this->assertSame( 'good', $result[ 'status' ] );
	}

	public function test_critical_signal_controls_zone_result_and_escapes_description() :void {
		$result = $this->builder(
			[ 'firewall' => 'Firewall' ],
			[
				$this->signal( 'firewall', 'WAF <script>', 'critical', false, [ 'Enable the firewall <script>' ] ),
				$this->signal( 'firewall', 'Rate limiting', 'warning', true ),
			]
		)->buildZoneResult( 'firewall' );

		$this->assertSame( 'critical', $result[ 'status' ] );
		$this->assertSame( [ 'label' => 'Security', 'color' => 'blue' ], $result[ 'badge' ] );
		$this->assertSame( 'shield_security_firewall', $result[ 'test' ] );
		$this->assertStringContainsString( '/admin/zones/overview?zone=firewall', $result[ 'actions' ] );
		$this->assertStringNotContainsString( '<script>', $result[ 'description' ] );
		$this->assertStringContainsString( '&lt;script&gt;', $result[ 'description' ] );
	}

	public function test_warning_or_unprotected_signal_maps_to_recommended() :void {
		$warningResult = $this->builder(
			[ 'login' => 'Login' ],
			[
				$this->signal( 'login', 'Login protection', 'warning', true ),
			]
		)->buildZoneResult( 'login' );

		$unprotectedResult = $this->builder(
			[ 'users' => 'Users' ],
			[
				$this->signal( 'users', 'Password policy', 'good', false ),
			]
		)->buildZoneResult( 'users' );

		$this->assertSame( 'recommended', $warningResult[ 'status' ] );
		$this->assertSame( 'recommended', $unprotectedResult[ 'status' ] );
	}

	public function test_protected_good_signals_map_to_good() :void {
		$result = $this->builder(
			[ 'headers' => 'HTTP Headers' ],
			[
				$this->signal( 'headers', 'Security headers', 'good', true ),
			]
		)->buildZoneResult( 'headers' );

		$this->assertSame( 'good', $result[ 'status' ] );
		$this->assertStringContainsString( '/admin/zones/overview?zone=headers', $result[ 'actions' ] );
	}

	public function test_unknown_zone_fails_loudly() :void {
		$this->expectException( \InvalidArgumentException::class );

		$this->builder( [ 'headers' => 'HTTP Headers' ], [] )->buildZoneResult( 'missing' );
	}

	private function builder( array $zoneTitles, array $signals ) :SiteHealthSecurityStatusBuilder {
		return new class( $zoneTitles, $signals ) extends SiteHealthSecurityStatusBuilder {
			private array $testZoneTitles;

			private array $testSignals;

			public function __construct( array $zoneTitles, array $signals ) {
				$this->testZoneTitles = $zoneTitles;
				$this->testSignals = $signals;
			}

			protected function zoneTitles() :array {
				return $this->testZoneTitles;
			}

			protected function buildZoneSignals() :array {
				return $this->testSignals;
			}
		};
	}

	private function signal(
		string $zone,
		string $title,
		string $severity,
		bool $isProtected,
		array $explanation = []
	) :array {
		return [
			'slug'          => sanitize_key( $title ),
			'title'         => $title,
			'weight'        => 1,
			'score'         => $isProtected ? 1 : 0,
			'is_protected'  => $isProtected,
			'severity'      => $severity,
			'explanation'   => $explanation,
			'config_action' => [],
			'zone'          => $zone,
		];
	}
}
