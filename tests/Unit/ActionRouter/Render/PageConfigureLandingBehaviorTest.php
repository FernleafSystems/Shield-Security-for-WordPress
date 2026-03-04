<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageConfigureLanding;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};

class PageConfigureLandingBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private object $renderCapture;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_landing_content_is_empty_for_posture_strip_layout() :void {
		$page = new PageConfigureLandingUnitTestDouble(
			$this->summaryMeterFixture( 78 ),
			$this->zoneTileFixtures()
		);
		$content = $this->invokeNonPublicMethod( $page, 'getLandingContent' );

		$this->assertSame( [], $content );
		$this->assertSame( [], $this->renderCapture->calls );
	}

	public function test_landing_vars_include_posture_strip_contract_and_zone_tiles() :void {
		$zoneTiles = $this->zoneTileFixtures();
		$page = new PageConfigureLandingUnitTestDouble( $this->summaryMeterFixture( 78 ), $zoneTiles );
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$this->assertSame( [], $this->renderCapture->calls );

		$this->assertArrayNotHasKey( 'configure_stats', $vars );
		$this->assertSame( 78, $vars[ 'posture_percentage' ] ?? 0 );
		$this->assertSame( 'warning', $vars[ 'posture_status' ] ?? '' );
		$this->assertSame( 'Warning', $vars[ 'posture_label' ] ?? '' );
		$this->assertSame( 'bi bi-exclamation-circle-fill', $vars[ 'posture_icon_class' ] ?? '' );
		$this->assertSame( '78% - 1 critical - 1 needs work - 1 good', $vars[ 'posture_summary' ] ?? '' );
		$this->assertSame( $zoneTiles, $vars[ 'zone_tiles' ] ?? [] );
	}

	public function test_mode_shell_contract_is_exposed_in_render_data() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->summaryMeterFixture( 78 ), $this->zoneTileFixtures() );
		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertSame( 'configure', $renderData[ 'vars' ][ 'mode_shell' ][ 'mode' ] ?? '' );
		$this->assertSame( 'good', $renderData[ 'vars' ][ 'mode_shell' ][ 'accent_status' ] ?? '' );
		$this->assertSame( 'compact', $renderData[ 'vars' ][ 'mode_shell' ][ 'header_density' ] ?? '' );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_mode_landing' ] ?? false ) );
		$this->assertTrue( (bool)( $renderData[ 'vars' ][ 'mode_shell' ][ 'is_interactive' ] ?? false ) );

		$this->assertCount( 3, $renderData[ 'vars' ][ 'mode_tiles' ] ?? [] );
		$this->assertSame( 'secadmin', $renderData[ 'vars' ][ 'mode_tiles' ][ 0 ][ 'key' ] ?? '' );
		$this->assertSame( 'secadmin', $renderData[ 'vars' ][ 'mode_tiles' ][ 0 ][ 'panel_target' ] ?? '' );
		$this->assertSame( 'firewall', $renderData[ 'vars' ][ 'mode_tiles' ][ 1 ][ 'key' ] ?? '' );
		$this->assertSame( 'spam', $renderData[ 'vars' ][ 'mode_tiles' ][ 2 ][ 'key' ] ?? '' );

		$this->assertSame( '', $renderData[ 'vars' ][ 'mode_panel' ][ 'active_target' ] ?? 'missing' );
		$this->assertFalse( (bool)( $renderData[ 'vars' ][ 'mode_panel' ][ 'is_open' ] ?? true ) );
	}

	public function test_landing_vars_use_zero_issue_breakdown_when_all_zones_are_good() :void {
		$page = new PageConfigureLandingUnitTestDouble(
			$this->summaryMeterFixture( 96 ),
			$this->allGoodZoneTileFixtures()
		);
		$vars = $this->invokeNonPublicMethod( $page, 'getLandingVars' );
		$this->assertSame( [], $this->renderCapture->calls );

		$this->assertSame( 96, $vars[ 'posture_percentage' ] ?? 0 );
		$this->assertSame( 'good', $vars[ 'posture_status' ] ?? '' );
		$this->assertSame( 'Good', $vars[ 'posture_label' ] ?? '' );
		$this->assertSame( 'bi bi-check-circle-fill', $vars[ 'posture_icon_class' ] ?? '' );
		$this->assertSame( '96% - 0 critical - 0 need work - 2 good', $vars[ 'posture_summary' ] ?? '' );
	}

	public function test_landing_hrefs_are_empty() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->summaryMeterFixture( 78 ), $this->zoneTileFixtures() );
		$hrefs = $this->invokeNonPublicMethod( $page, 'getLandingHrefs' );
		$this->assertSame( [], $hrefs );
	}

	public function test_landing_strings_include_current_headings_only() :void {
		$page = new PageConfigureLandingUnitTestDouble( $this->summaryMeterFixture( 78 ), $this->zoneTileFixtures() );
		$strings = $this->invokeNonPublicMethod( $page, 'getLandingStrings' );

		$this->assertSame( 'Configuration Posture', $strings[ 'posture_title' ] ?? '' );
		$this->assertSame( 'Security Zones', $strings[ 'zones_title' ] ?? '' );
		$this->assertSame( 'Jump directly to a security zone to review and adjust settings.', $strings[ 'zones_subtitle' ] ?? '' );

		foreach ( [ 'stats_title', 'overview_title', 'quick_links_title', 'link_grades', 'link_zones', 'link_rules', 'link_tools' ] as $removedKey ) {
			$this->assertArrayNotHasKey( $removedKey, $strings );
		}
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   stat_line:string,
	 *   settings_href:string,
	 *   settings_label:string,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{title:string,status:string,status_label:string,note:string}>
	 *   }
	 * }>
	 */
	private function zoneTileFixtures() :array {
		return [
			$this->buildZoneTileFixture(
				'secadmin',
				'Security Admin',
				'shield-lock',
				'good',
				'Good',
				'All components healthy',
				'/admin/zones/secadmin',
				'Configure Security Admin Settings',
				[
					$this->buildZoneComponentFixture( 'PIN Protection', 'good', 'Active', 'PIN is configured.' ),
				]
			),
			$this->buildZoneTileFixture(
				'firewall',
				'Firewall',
				'fire',
				'warning',
				'Needs Work',
				'1 component needs work',
				'/admin/zones/firewall',
				'Configure Firewall Settings',
				[
					$this->buildZoneComponentFixture( 'WAF Rules', 'warning', 'Needs Work', 'One rule requires review.' ),
				]
			),
			$this->buildZoneTileFixture(
				'spam',
				'Comments Filter',
				'chat-dots',
				'critical',
				'Critical',
				'1 critical component',
				'/admin/zones/spam',
				'Configure Comments Filter Settings',
				[
					$this->buildZoneComponentFixture( 'Spam Filter', 'critical', 'Issue', 'Spam filter requires setup.' ),
				]
			),
		];
	}

	/**
	 * @return list<array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   stat_line:string,
	 *   settings_href:string,
	 *   settings_label:string,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{title:string,status:string,status_label:string,note:string}>
	 *   }
	 * }>
	 */
	private function allGoodZoneTileFixtures() :array {
		return [
			$this->buildZoneTileFixture(
				'secadmin',
				'Security Admin',
				'shield-lock',
				'good',
				'Good',
				'All components healthy',
				'/admin/zones/secadmin',
				'Configure Security Admin Settings',
				[
					$this->buildZoneComponentFixture( 'PIN Protection', 'good', 'Active', 'PIN is configured.' ),
				]
			),
			$this->buildZoneTileFixture(
				'login',
				'Login Protection',
				'person-lock',
				'good',
				'Good',
				'All components healthy',
				'/admin/zones/login',
				'Configure Login Protection Settings',
				[
					$this->buildZoneComponentFixture( '2FA', 'good', 'Active', '2FA is enforced.' ),
				]
			),
		];
	}

	/**
	 * @param list<array{title:string,status:string,status_label:string,note:string}> $components
	 * @return array{
	 *   key:string,
	 *   panel_target:string,
	 *   is_enabled:bool,
	 *   is_disabled:bool,
	 *   label:string,
	 *   icon_class:string,
	 *   status:string,
	 *   status_label:string,
	 *   stat_line:string,
	 *   settings_href:string,
	 *   settings_label:string,
	 *   panel:array{
	 *     title:string,
	 *     status:string,
	 *     status_label:string,
	 *     components:list<array{title:string,status:string,status_label:string,note:string}>
	 *   }
	 * }
	 */
	private function buildZoneTileFixture(
		string $key,
		string $label,
		string $icon,
		string $status,
		string $statusLabel,
		string $statLine,
		string $settingsHref,
		string $settingsLabel,
		array $components
	) :array {
		return [
			'key'            => $key,
			'panel_target'   => $key,
			'is_enabled'     => true,
			'is_disabled'    => false,
			'label'          => $label,
			'icon_class'     => 'bi bi-'.$icon,
			'status'         => $status,
			'status_label'   => $statusLabel,
			'stat_line'      => $statLine,
			'settings_href'  => $settingsHref,
			'settings_label' => $settingsLabel,
			'panel'          => [
				'title'        => $label,
				'status'       => $status,
				'status_label' => $statusLabel,
				'components'   => $components,
			],
		];
	}

	/**
	 * @return array{title:string,status:string,status_label:string,note:string}
	 */
	private function buildZoneComponentFixture(
		string $title,
		string $status,
		string $statusLabel,
		string $note
	) :array {
		return [
			'title'        => $title,
			'status'       => $status,
			'status_label' => $statusLabel,
			'note'         => $note,
		];
	}

	private function summaryMeterFixture( int $percentage ) :array {
		return [
			'totals' => [ 'percentage' => $percentage ],
		];
	}

	private function installControllerStub() :void {
		$this->renderCapture = (object)[
			'calls' => [],
		];

		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		$controller->action_router = new class( $this->renderCapture ) {
			private object $capture;

			public function __construct( object $capture ) {
				$this->capture = $capture;
			}

			public function render( string $action, array $actionData = [] ) :string {
				$this->capture->calls[] = [
					'action'      => $action,
					'action_data' => $actionData,
				];
				return 'rendered-'.\count( $this->capture->calls );
			}
		};
		PluginControllerInstaller::install( $controller );
	}
}

class PageConfigureLandingUnitTestDouble extends PageConfigureLanding {

	private array $summaryMeterFixture;

	private array $zoneTileFixtures;

	public function __construct( array $summaryMeterFixture, array $zoneTileFixtures ) {
		$this->summaryMeterFixture = $summaryMeterFixture;
		$this->zoneTileFixtures = $zoneTileFixtures;
	}

	protected function getSummaryMeterData() :array {
		return $this->summaryMeterFixture;
	}

	protected function getConfigureZoneTiles() :array {
		return $this->zoneTileFixtures;
	}
}
