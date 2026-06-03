<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\SiteHealth;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteHealth\{
	SiteHealthSecurityStatusBuilder,
	SiteHealthSecurityTabRenderer
};
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
		Functions\when( '_n' )->alias( static fn( string $single, string $plural, int $number ) :string => $number === 1 ? $single : $plural );
		Functions\when( 'esc_attr' )->alias(
			static fn( string $text ) :string => \htmlspecialchars( $text, \ENT_QUOTES )
		);
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

	public function test_build_tests_registers_one_aggregate_callback() :void {
		$tests = $this->builder(
			[
				'secadmin' => 'Security Admin',
				'firewall' => 'Firewall',
			],
			[]
		)->buildTests( '/wp-admin/site-health.php?tab=shield_security' );

		$this->assertSame( [ 'shield_security' ], \array_keys( $tests ) );
		$this->assertSame( 'Shield Security', $tests[ 'shield_security' ][ 'label' ] );
		$this->assertTrue( $tests[ 'shield_security' ][ 'skip_cron' ] );
		$this->assertIsCallable( $tests[ 'shield_security' ][ 'test' ] );

		$result = \call_user_func( $tests[ 'shield_security' ][ 'test' ] );

		$this->assertSame( 'shield_security', $result[ 'test' ] );
		$this->assertSame( 'good', $result[ 'status' ] );
		$this->assertStringContainsString( '/wp-admin/site-health.php?tab=shield_security', $result[ 'actions' ] );
	}

	public function test_aggregate_status_uses_highest_zone_status() :void {
		$criticalResult = $this->builder(
			[
				'firewall' => 'Firewall',
				'login'    => 'Login',
			],
			[
				$this->signal( 'firewall', 'WAF', 'critical', false ),
				$this->signal( 'login', 'Login protection', 'warning', true ),
			]
		)->buildAggregateResult( '/details' );

		$recommendedResult = $this->builder(
			[
				'login' => 'Login',
				'users' => 'Users',
			],
			[
				$this->signal( 'login', 'Login protection', 'warning', true ),
				$this->signal( 'users', 'Password policy', 'good', true ),
			]
		)->buildAggregateResult( '/details' );

		$goodResult = $this->builder(
			[ 'headers' => 'HTTP Headers' ],
			[
				$this->signal( 'headers', 'Security headers', 'good', true ),
			]
		)->buildAggregateResult( '/details' );

		$this->assertSame( 'critical', $criticalResult[ 'status' ] );
		$this->assertSame( 'recommended', $recommendedResult[ 'status' ] );
		$this->assertSame( 'good', $goodResult[ 'status' ] );
	}

	public function test_tab_groups_use_zone_results_and_escape_descriptions() :void {
		$groups = $this->builder(
			[
				'firewall' => 'Firewall',
				'login'    => 'Login',
				'headers'  => 'HTTP Headers',
			],
			[
				$this->signal( 'firewall', 'WAF <script>', 'critical', false, [ 'Enable <script>' ] ),
				$this->signal( 'login', 'Login protection', 'warning', true ),
				$this->signal( 'headers', 'Security headers', 'good', true ),
			]
		)->buildTabGroups();

		$this->assertSame( [ 'critical', 'recommended', 'good' ], \array_keys( $groups ) );
		$this->assertSame( 'firewall', $groups[ 'critical' ][ 'items' ][ 0 ][ 'slug' ] );
		$this->assertSame( 'login', $groups[ 'recommended' ][ 'items' ][ 0 ][ 'slug' ] );
		$this->assertSame( 'headers', $groups[ 'good' ][ 'items' ][ 0 ][ 'slug' ] );
		$this->assertStringContainsString( '/admin/zones/overview?zone=firewall', $groups[ 'critical' ][ 'items' ][ 0 ][ 'actions' ] );
		$this->assertStringNotContainsString( '<script>', $groups[ 'critical' ][ 'items' ][ 0 ][ 'description' ] );
		$this->assertStringContainsString( '&lt;script&gt;', $groups[ 'critical' ][ 'items' ][ 0 ][ 'description' ] );
	}

	public function test_renderer_outputs_stable_panels_without_unescaped_signal_text() :void {
		$html = ( new SiteHealthSecurityTabRenderer( $this->builder(
			[ 'firewall' => 'Firewall' ],
			[
				$this->signal( 'firewall', 'WAF <script>', 'critical', false, [ 'Enable <script>' ] ),
			]
		) ) )->render();

		$this->assertStringContainsString( 'health-check-accordion-block-shield_firewall', $html );
		$this->assertStringContainsString( 'WAF &lt;script&gt;', $html );
		$this->assertStringNotContainsString( 'WAF <script>', $html );
		$this->assertStringNotContainsString( 'Enable <script>', $html );
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
