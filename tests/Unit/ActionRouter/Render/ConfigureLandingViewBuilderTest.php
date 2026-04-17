<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ConfigureLandingViewBuilder,
	ConfigureZoneTilesBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestPluginUrls
};
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\BuildZonePosture;

class ConfigureLandingViewBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text )
				? ( \preg_replace( '/[^a-z0-9_-]/', '', \strtolower( \trim( $text ) ) ) ?? '' )
				: ''
		);
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_build_returns_converged_landing_contract() :void {
		$view = ( new ConfigureLandingViewBuilder(
			new class( $this->baseZoneTileFixtures() ) extends ConfigureZoneTilesBuilder {
				private array $tiles;

				public function __construct( array $tiles ) {
					$this->tiles = $tiles;
				}

				public function build() :array {
					return $this->tiles;
				}
			},
			null,
			null,
			new class( $this->zonePostureFixture( 78 ) ) extends BuildZonePosture {
				private array $posture;

				public function __construct( array $posture ) {
					$this->posture = $posture;
				}

				public function build() :array {
					return $this->posture;
				}
			}
		) )->build();

		$this->assertSame( [ 'secadmin', 'firewall', 'login', 'general' ], \array_column( $view[ 'tiles' ], 'key' ) );
		$this->assertSame( [ 'secadmin', 'firewall', 'login', 'general' ], \array_keys( $view[ 'tile_lookup' ] ) );
		$this->assertSame( [ 'secadmin', 'firewall', 'login', 'general' ], \array_keys( $view[ 'diagnoses' ] ) );
		$this->assertNotEmpty( $view[ 'tiles' ][ 0 ][ 'panel' ][ 'detail_groups' ] ?? [] );
		$this->assertSame(
			[ 'critical', 'warning', 'general', 'healthy' ],
			\array_column( $view[ 'sections' ], 'key' )
		);
		$this->assertSame(
			[ 'secadmin' ],
			\array_column( $view[ 'sections' ][ 0 ][ 'cards' ] ?? [], 'key' )
		);
		$this->assertSame(
			[ 'login' ],
			\array_column( $view[ 'sections' ][ 1 ][ 'cards' ] ?? [], 'key' )
		);
		$this->assertSame(
			[ 'general' ],
			\array_column( $view[ 'sections' ][ 2 ][ 'cards' ] ?? [], 'key' )
		);
		$this->assertSame(
			[ 'firewall' ],
			\array_column( $view[ 'sections' ][ 3 ][ 'cards' ] ?? [], 'key' )
		);
		$this->assertSame(
			$view[ 'tiles' ][ 0 ][ 'summary' ] ?? '',
			$view[ 'sections' ][ 0 ][ 'cards' ][ 0 ][ 'summary' ] ?? ''
		);
		$this->assertNotSame(
			$view[ 'diagnoses' ][ 'secadmin' ][ 'preview_text' ] ?? '',
			$view[ 'sections' ][ 0 ][ 'cards' ][ 0 ][ 'summary' ] ?? ''
		);
		$this->assertCount( 1, $view[ 'diagnoses' ][ 'secadmin' ][ 'header' ][ 'actions' ] ?? [] );
		$this->assertSame(
			'Disable Security Admin',
			$view[ 'diagnoses' ][ 'secadmin' ][ 'header' ][ 'actions' ][ 0 ][ 'label' ] ?? ''
		);
		$this->assertSame( '78% - 1 critical - 1 needs work - 1 good', $view[ 'posture_summary' ][ 'summary' ] ?? '' );
		$this->assertSame( 'Warning', $view[ 'posture_summary' ][ 'chip_label' ] ?? '' );
		$this->assertSame( '78%', $view[ 'root_step' ][ 'badge' ] ?? '' );
		$this->assertSame( 'warning', $view[ 'root_step' ][ 'badge_status' ] ?? '' );
		$this->assertSame( 'configure', $view[ 'root_step' ][ 'color_key' ] ?? '' );
		$this->assertSame(
			$view[ 'root_step' ],
			\json_decode( $view[ 'root_step_json' ] ?? '', true )
		);
	}

	private function baseZoneTileFixtures() :array {
		return [
			$this->buildZoneTileFixture(
				'secadmin',
				'Security Admin',
				'critical',
				'Critical',
				'Protect plugin and core admin settings with an extra admin security layer.',
				'1 critical group',
				[
					$this->buildZoneRowFixture(
						'PIN Protection',
						'critical',
						'Issue',
						'PIN is not configured.',
						[ 'Set a PIN before more admins are added.' ]
					),
				]
			),
			$this->buildZoneTileFixture(
				'firewall',
				'Firewall',
				'good',
				'Good',
				'Stabilized firewall summary.',
				'All groups healthy',
				[
					$this->buildZoneRowFixture(
						'WAF Rules',
						'good',
						'Active',
						'Firewall rules are active.'
					),
				]
			),
			$this->buildZoneTileFixture(
				'login',
				'Login',
				'warning',
				'Needs Work',
				'Stabilized login summary.',
				'1 group needs work',
				[
					$this->buildZoneRowFixture(
						'2FA',
						'warning',
						'Needs Work',
						'2FA requires review.',
						[ 'Require 2FA for administrators.' ]
					),
				]
			),
			$this->buildZoneTileFixture(
				'general',
				'General',
				'neutral',
				'General',
				'Stabilized general summary.',
				'General settings',
				[
					$this->buildZoneRowFixture(
						'Traffic Logging',
						'neutral',
						'General',
						'General settings'
					),
				],
				false
			),
		];
	}

	private function buildZoneTileFixture(
		string $key,
		string $label,
		string $status,
		string $statusLabel,
		string $summary,
		string $statLine,
		array $rows,
		bool $includeInPosture = true
	) :array {
		return [
			'key'                => $key,
			'panel_target'       => $key,
			'is_enabled'         => true,
			'is_disabled'        => false,
			'include_in_posture' => $includeInPosture,
			'label'              => $label,
			'icon_class'         => 'bi bi-gear',
			'summary'            => $summary,
			'status'             => $status,
			'status_label'       => $statusLabel,
			'status_icon_class'  => 'bi bi-shield-check',
			'stat_line'          => $statLine,
			'panel'              => [
				'title'        => $label,
				'status'       => $status,
				'status_label' => $statusLabel,
				'rows'         => $rows,
			],
		];
	}

	private function buildZoneRowFixture(
		string $title,
		string $status,
		string $statusLabel,
		string $note,
		array $explanations = []
	) :array {
		return [
			'key'               => \strtolower( \str_replace( ' ', '_', $title ) ),
			'title'             => $title,
			'status'            => $status,
			'status_label'      => $statusLabel,
			'status_icon_class' => 'bi bi-exclamation-triangle-fill',
			'note'              => $note,
			'explanations'      => $explanations,
			'config_action'     => [
				'title'   => 'Configure '.$title,
				'href'    => 'javascript:{}',
				'icon'    => 'bi bi-gear-fill',
				'tooltip' => '',
				'classes' => [ 'zone_component_action' ],
				'data'    => [
					'zone_component_action' => 'offcanvas_zone_component_config',
					'zone_component_slug'   => \strtolower( \str_replace( ' ', '_', $title ) ),
					'form_context'          => 'offcanvas',
				],
			],
		];
	}

	private function zonePostureFixture( int $percentage ) :array {
		return [
			'components' => [],
			'signals'    => [],
			'totals'     => [
				'score'        => $percentage,
				'max_weight'   => 100,
				'percentage'   => $percentage,
				'letter_score' => 'B',
			],
			'percentage' => $percentage,
			'severity'   => 'warning',
			'status'     => 'warning',
		];
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new UnitTestPluginUrls();
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->comps = (object)[
			'sec_admin' => new class {
				public function isEnabledSecAdmin() :bool {
					return true;
				}
			},
		];
		PluginControllerInstaller::install( $controller );
	}
}
