<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\CloakedPlugins;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakReason,
	PluginEntry,
	PluginPageView,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestPluginUrls,
	UnitTestRequest
};

class PluginPageViewTest extends BaseUnitTest {

	private array $servicesSnapshot = [];
	private array $activeFindings = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'number_format_i18n' )->alias( static fn( int $number ) :string => (string)$number );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest(),
		] );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		unset( $GLOBALS[ 'status' ] );
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_status_link_is_added_only_for_active_findings() :void {
		$this->activeFindings = [ $this->standardFinding( 'cloaked/cloaked.php', 'Cloaked Plugin' ) ];
		$GLOBALS[ 'status' ] = PluginPageView::STATUS;

		$views = ( new PluginPageView() )->addStatusViewLink( [ 'all' => '<a>All</a>' ] );

		$this->assertArrayHasKey( PluginPageView::STATUS, $views );
		$this->assertStringContainsString( '/wp-admin/plugins.php?plugin_status=cloaked', $views[ PluginPageView::STATUS ] );
		$this->assertStringContainsString( 'class="current"', $views[ PluginPageView::STATUS ] );
		$this->assertStringContainsString( '(1)', $views[ PluginPageView::STATUS ] );
	}

	public function test_status_link_is_not_added_without_active_findings() :void {
		$this->activeFindings = [];

		$views = ( new PluginPageView() )->addStatusViewLink( [ 'all' => '<a>All</a>' ] );

		$this->assertArrayNotHasKey( PluginPageView::STATUS, $views );
	}

	public function test_plugins_list_adds_cloaked_bucket_without_mutating_existing_buckets() :void {
		$standard = $this->standardFinding( 'cloaked/cloaked.php', 'Cloaked Plugin' );
		$mustUse = $this->mustUseFinding( 'mu-loader.php', 'MU Loader' );
		$this->activeFindings = [ $standard, $mustUse ];
		$plugins = [
			'all'     => [
				'cloaked/cloaked.php' => [
					'Name'    => 'Existing Cloaked Plugin',
					'Version' => '9.9.9',
				],
			],
			'mustuse' => [],
		];

		$result = ( new PluginPageView() )->addCloakedList( $plugins );

		$this->assertSame( $plugins[ 'all' ], $result[ 'all' ] );
		$this->assertSame( 'Existing Cloaked Plugin', $result[ PluginPageView::STATUS ][ $standard->entry->file ][ 'Name' ] );
		$this->assertSame( 'MU Loader', $result[ PluginPageView::STATUS ][ $mustUse->entry->file ][ 'Name' ] );
		$this->assertStringContainsString( 'Must-Use', $result[ PluginPageView::STATUS ][ $mustUse->entry->file ][ 'Description' ] );
	}

	public function test_cloaked_status_request_sets_global_status() :void {
		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest( [
				'plugin_status' => PluginPageView::STATUS,
			] ),
		] );

		( new PluginPageView() )->setCurrentStatus();

		$this->assertSame( PluginPageView::STATUS, $GLOBALS[ 'status' ] ?? '' );
	}

	public function test_must_use_actions_are_stripped_only_on_cloaked_status() :void {
		$mustUse = $this->mustUseFinding( 'mu-loader.php', 'MU Loader' );
		$this->activeFindings = [ $mustUse ];
		$actions = [ 'deactivate' => '<a>Deactivate</a>' ];

		$this->assertSame( $actions, ( new PluginPageView() )->filterActionLinks( $actions, $mustUse->entry->file ) );

		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest( [
				'plugin_status' => PluginPageView::STATUS,
			] ),
		] );

		$this->assertSame( [], ( new PluginPageView() )->filterActionLinks( $actions, $mustUse->entry->file ) );
	}

	private function standardFinding( string $file, string $name ) :CloakedPluginFinding {
		return new CloakedPluginFinding(
			new PluginEntry( PluginType::Standard, $file, $name, '1.0', '/plugins/'.$file ),
			[ CloakReason::AllPlugins ],
			false,
			false,
			123
		);
	}

	private function mustUseFinding( string $file, string $name ) :CloakedPluginFinding {
		return new CloakedPluginFinding(
			new PluginEntry( PluginType::MustUse, $file, $name, '1.0', '/mu-plugins/'.$file ),
			[ CloakReason::ShowAdvancedPlugins ],
			true,
			false,
			123
		);
	}

	public function activeFindings() :array {
		return $this->activeFindings;
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new UnitTestPluginUrls();
		$controller->comps = (object)[
			'hidden_plugins' => new class( $this ) {
				private PluginPageViewTest $test;

				public function __construct( PluginPageViewTest $test ) {
					$this->test = $test;
				}

				public function currentState() :array {
					return [
						'all'               => $this->test->activeFindings(),
						'active'            => $this->test->activeFindings(),
						'ignored'           => [],
						'system_suppressed' => [],
						'new_active'        => [],
					];
				}
			},
		];
		PluginControllerInstaller::install( $controller );
	}
}
