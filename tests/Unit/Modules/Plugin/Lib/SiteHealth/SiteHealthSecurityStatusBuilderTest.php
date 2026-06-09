<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Modules\Plugin\Lib\SiteHealth;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneTilesBuilder;
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
		Functions\when( 'sanitize_key' )->alias(
			static function ( $text ) :string {
				if ( !\is_string( $text ) ) {
					return '';
				}
				$normalized = \preg_replace( '/[^a-z0-9_-]/', '', \strtolower( \trim( $text ) ) );
				return $normalized === null ? '' : $normalized;
			}
		);

		UnitTestControllerFactory::install( new UnitTestPluginUrls() );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_tests_registers_one_aggregate_callback() :void {
		$tests = $this->builder( [
			$this->tile( 'secadmin', 'Security Admin', 'good' ),
			$this->tile( 'firewall', 'Firewall', 'good' ),
		] )->buildTests( '/wp-admin/site-health.php?tab=shield_security' );

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
		$criticalResult = $this->builder( [
			$this->tile( 'firewall', 'Firewall', 'critical', [
				$this->row( 'WAF Rules', 'critical', 'Primary WAF rules are disabled.' ),
			] ),
			$this->tile( 'login', 'Login', 'warning', [
				$this->row( 'Login protection', 'warning', 'Login protection needs review.' ),
			] ),
		] )->buildAggregateResult( '/details' );

		$recommendedResult = $this->builder( [
			$this->tile( 'login', 'Login', 'warning', [
				$this->row( 'Login protection', 'warning', 'Login protection needs review.' ),
			] ),
			$this->tile( 'users', 'Users', 'good' ),
		] )->buildAggregateResult( '/details' );

		$goodResult = $this->builder( [
			$this->tile( 'headers', 'HTTP Headers', 'good' ),
		] )->buildAggregateResult( '/details' );

		$this->assertSame( 'critical', $criticalResult[ 'status' ] );
		$this->assertSame( 'recommended', $recommendedResult[ 'status' ] );
		$this->assertSame( 'good', $goodResult[ 'status' ] );
	}

	public function test_build_zone_statuses_excludes_non_posture_tiles() :void {
		$zones = $this->builder( [
			$this->tile( 'general', 'General', 'neutral', [], false ),
			$this->tile( 'firewall', 'Firewall', 'good' ),
		] )->buildZoneStatuses();

		$this->assertSame( [ 'firewall' ], \array_column( $zones, 'slug' ) );
	}

	public function test_tab_groups_use_zone_results_and_escape_descriptions() :void {
		$groups = $this->builder( [
			$this->tile( 'firewall', 'Firewall', 'critical', [
				$this->row( 'WAF <script>', 'critical', '', [ 'Enable <script>' ] ),
			] ),
			$this->tile( 'login', 'Login', 'warning', [
				$this->row( 'Login protection', 'warning', 'Login protection needs review.' ),
			] ),
			$this->tile( 'headers', 'HTTP Headers', 'good' ),
		] )->buildTabGroups();

		$this->assertSame( [ 'critical', 'recommended', 'good' ], \array_keys( $groups ) );
		$this->assertSame( 'firewall', $groups[ 'critical' ][ 'items' ][ 0 ][ 'slug' ] );
		$this->assertSame( 'login', $groups[ 'recommended' ][ 'items' ][ 0 ][ 'slug' ] );
		$this->assertSame( 'headers', $groups[ 'good' ][ 'items' ][ 0 ][ 'slug' ] );
		$this->assertStringContainsString( '/admin/zones/overview?zone=firewall', $groups[ 'critical' ][ 'items' ][ 0 ][ 'actions' ] );
		$this->assertStringNotContainsString( '<script>', $groups[ 'critical' ][ 'items' ][ 0 ][ 'description' ] );
		$this->assertStringContainsString( '&lt;script&gt;', $groups[ 'critical' ][ 'items' ][ 0 ][ 'description' ] );
	}

	public function test_renderer_outputs_stable_panels_without_unescaped_row_text() :void {
		$html = ( new SiteHealthSecurityTabRenderer( $this->builder( [
			$this->tile( 'firewall', 'Firewall', 'critical', [
				$this->row( 'WAF <script>', 'critical', '', [ 'Enable <script>' ] ),
			] ),
		] ) ) )->render();

		$this->assertStringContainsString( 'health-check-accordion-block-shield_firewall', $html );
		$this->assertStringContainsString( 'WAF &lt;script&gt;', $html );
		$this->assertStringNotContainsString( 'WAF <script>', $html );
		$this->assertStringNotContainsString( 'Enable <script>', $html );
	}

	private function builder( array $tiles ) :SiteHealthSecurityStatusBuilder {
		return new SiteHealthSecurityStatusBuilder(
			new class( $tiles ) extends ConfigureZoneTilesBuilder {
				private array $tiles;

				public function __construct( array $tiles ) {
					$this->tiles = $tiles;
				}

				public function build() :array {
					return $this->tiles;
				}
			}
		);
	}

	private function tile(
		string $key,
		string $label,
		string $status,
		array $rows = [],
		bool $includeInPosture = true
	) :array {
		return [
			'key'                => $key,
			'panel_target'       => $key,
			'is_enabled'         => true,
			'is_disabled'        => false,
			'include_in_posture' => $includeInPosture,
			'label'              => $label,
			'icon_class'         => 'bi bi-shield',
			'summary'            => $label.' summary',
			'status'             => $status,
			'status_label'       => $status,
			'status_icon_class'  => 'bi bi-check-circle-fill',
			'stat_line'          => 'All groups healthy',
			'panel'              => [
				'title'        => $label,
				'status'       => $status,
				'status_label' => $status,
				'rows'         => $rows,
			],
		];
	}

	private function row(
		string $title,
		string $status,
		string $note = '',
		array $explanations = []
	) :array {
		return [
			'key'               => sanitize_key( $title ),
			'title'             => $title,
			'status'            => $status,
			'status_label'      => $status,
			'status_icon_class' => 'bi bi-exclamation-triangle-fill',
			'note'              => $note,
			'explanations'      => $explanations,
			'config_action'     => [],
		];
	}
}
