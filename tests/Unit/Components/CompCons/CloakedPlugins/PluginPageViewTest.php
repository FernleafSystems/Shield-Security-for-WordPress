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
use FernleafSystems\Wordpress\Services\Core\Fs;

class PluginPageViewTest extends BaseUnitTest {

	private array $servicesSnapshot = [];
	private array $activeFindings = [];
	private array $ignoredFindings = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( string $path ) :string => \str_replace( '\\', '/', $path )
		);
		Functions\when( 'number_format_i18n' )->alias( static fn( int $number ) :string => (string)$number );
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest(),
			'service_wpfs'    => new Fs(),
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

		$views = $this->viewShowingTheCloakedList()->addStatusViewLink( [ 'all' => '<a>All</a>' ] );

		$this->assertArrayHasKey( PluginPageView::STATUS, $views );
		$this->assertStringContainsString( '/wp-admin/plugins.php?plugin_status=cloaked', $views[ PluginPageView::STATUS ] );
		$this->assertStringContainsString( 'class="current"', $views[ PluginPageView::STATUS ] );
		$this->assertStringContainsString( '(1)', $views[ PluginPageView::STATUS ] );
	}

	public function test_status_link_is_not_marked_current_on_another_list() :void {
		$this->activeFindings = [ $this->standardFinding( 'cloaked/cloaked.php', 'Cloaked Plugin' ) ];
		$view = new PluginPageView();
		$view->addCloakedList( [ 'all' => [] ] );

		$views = $view->addStatusViewLink( [ 'all' => '<a>All</a>' ] );

		$this->assertArrayHasKey( PluginPageView::STATUS, $views );
		$this->assertStringNotContainsString( 'class="current"', $views[ PluginPageView::STATUS ] );
	}

	public function test_status_link_is_not_added_without_active_findings() :void {
		$this->activeFindings = [];

		$views = ( new PluginPageView() )->addStatusViewLink( [ 'all' => '<a>All</a>' ] );

		$this->assertArrayNotHasKey( PluginPageView::STATUS, $views );
	}

	public function test_status_link_is_not_added_for_ignored_findings_without_active_findings() :void {
		$this->activeFindings = [];
		$this->ignoredFindings = [ $this->standardFinding( 'ignored/ignored.php', 'Ignored Plugin' ) ];

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
					'Name'        => 'Existing Cloaked Plugin',
					'Version'     => '9.9.9',
					'Description' => 'Opaque plugin header copy.',
				],
			],
			'mustuse' => [],
		];

		$result = ( new PluginPageView() )->addCloakedList( $plugins );

		$this->assertSame( $plugins[ 'all' ], $result[ 'all' ] );
		$this->assertSame( 'Existing Cloaked Plugin', $result[ PluginPageView::STATUS ][ $standard->entry->file ][ 'Name' ] );
		$this->assertStringNotContainsString( 'Opaque plugin header copy', $result[ PluginPageView::STATUS ][ $standard->entry->file ][ 'Description' ] );
		$this->assertNotSame( '', $result[ PluginPageView::STATUS ][ $standard->entry->file ][ 'Description' ] );
		$this->assertSame( 'MU Loader', $result[ PluginPageView::STATUS ][ $mustUse->entry->file ][ 'Name' ] );
		$this->assertNotSame( '', $result[ PluginPageView::STATUS ][ $mustUse->entry->file ][ 'Description' ] );
	}

	public function test_row_meta_adds_relative_file_and_reason_only_on_cloaked_status() :void {
		$finding = $this->standardFinding( 'cloaked/cloaked.php', 'Cloaked Plugin' );
		$this->activeFindings = [ $finding ];
		$meta = [ 'version' => 'Version 1.0' ];

		$onAnotherList = new PluginPageView();
		$onAnotherList->addCloakedList( [ 'all' => [] ] );
		$this->assertSame( $meta, $onAnotherList->addRowMeta( $meta, $finding->entry->file ) );

		$result = $this->viewShowingTheCloakedList()->addRowMeta( $meta, $finding->entry->file );

		$this->assertStringContainsString( '<code>wp-content/plugins/cloaked/cloaked.php</code>', $result[ 'shield-cloaked-path' ] );
		$this->assertNotSame( '', $result[ 'shield-cloaked-reason' ] );
	}

	/**
	 * To draw a row's update notice, WordPress works out the table's list again from the request alone, which up to
	 * 6.9 puts it back on "all" for the rows that follow. Those rows are still rows of the cloaked list.
	 */
	public function test_rows_stay_rows_of_the_cloaked_list_when_wordpress_changes_its_list_part_way_down_the_table() :void {
		$standard = $this->standardFinding( 'cloaked/cloaked.php', 'Cloaked Plugin' );
		$mustUse = $this->mustUseFinding( 'mu-loader.php', 'MU Loader' );
		$this->activeFindings = [ $standard, $mustUse ];
		$view = $this->viewShowingTheCloakedList();

		$GLOBALS[ 'status' ] = 'all';

		$this->assertArrayHasKey( 'shield-cloaked-path', $view->addRowMeta( [], $standard->entry->file ) );
		$this->assertSame( [], $view->filterActionLinks( [ 'activate' => '<a>Activate</a>' ], $mustUse->entry->file ) );
	}

	/**
	 * From WordPress 7.0 the list is chosen by WordPress itself, and it takes the request's spelling of it more
	 * loosely than an exact match.
	 */
	public function test_a_table_wordpress_already_has_on_the_cloaked_list_is_showing_it() :void {
		$mustUse = $this->mustUseFinding( 'mu-loader.php', 'MU Loader' );
		$this->activeFindings = [ $mustUse ];
		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest( [
				'plugin_status' => 'CLOAKED',
			] ),
		] );
		$GLOBALS[ 'status' ] = PluginPageView::STATUS;
		$view = new PluginPageView();
		$view->addCloakedList( [ 'all' => [] ] );

		$this->assertArrayHasKey( 'shield-cloaked-path', $view->addRowMeta( [], $mustUse->entry->file ) );
		$this->assertSame( [], $view->filterActionLinks( [ 'activate' => '<a>Activate</a>' ], $mustUse->entry->file ) );
	}

	/**
	 * The same redrawing, from WordPress 7.0, sets WordPress's note of the page's list to the one a search was
	 * started from. The table's rows are still search results.
	 */
	public function test_rows_stay_search_results_when_wordpress_changes_its_list_part_way_down_the_table() :void {
		$mustUse = $this->mustUseFinding( 'mu-loader.php', 'MU Loader' );
		$this->activeFindings = [ $mustUse ];
		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest( [
				'plugin_status' => PluginPageView::STATUS,
				's'             => 'something',
			] ),
		] );
		$GLOBALS[ 'status' ] = 'search';
		$view = new PluginPageView();
		$view->addCloakedList( [ 'all' => [], 'search' => [] ] );
		$actions = [ 'activate' => '<a>Activate</a>' ];

		$GLOBALS[ 'status' ] = PluginPageView::STATUS;

		$this->assertSame( [], $view->addRowMeta( [], $mustUse->entry->file ) );
		$this->assertSame( $actions, $view->filterActionLinks( $actions, $mustUse->entry->file ) );
	}

	/**
	 * The table's list is chosen as the cloaked list is added, which is before WordPress picks the table's rows.
	 * Up to WordPress 6.9 the table starts on "all" for a list it doesn't know, as it does here.
	 */
	public function test_a_request_for_the_cloaked_list_has_the_table_show_it() :void {
		$this->activeFindings = [ $this->standardFinding( 'cloaked/cloaked.php', 'Cloaked Plugin' ) ];
		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest( [
				'plugin_status' => PluginPageView::STATUS,
			] ),
		] );
		$GLOBALS[ 'status' ] = 'all';

		( new PluginPageView() )->addCloakedList( [ 'all' => [] ] );

		$this->assertSame( PluginPageView::STATUS, $GLOBALS[ 'status' ] );
	}

	/**
	 * When the page's search box is cleared, WordPress fetches the rows again in the background and posts the list
	 * the page is on.
	 */
	public function test_a_posted_request_for_the_cloaked_list_has_the_table_show_it() :void {
		$this->activeFindings = [ $this->standardFinding( 'cloaked/cloaked.php', 'Cloaked Plugin' ) ];
		$request = new UnitTestRequest();
		$request->post = [ 'plugin_status' => PluginPageView::STATUS ];
		ServicesState::mergeItems( [ 'service_request' => $request ] );
		$GLOBALS[ 'status' ] = 'all';

		( new PluginPageView() )->addCloakedList( [ 'all' => [] ] );

		$this->assertSame( PluginPageView::STATUS, $GLOBALS[ 'status' ] );
	}

	public function test_a_request_for_the_cloaked_list_changes_nothing_when_nothing_is_cloaked() :void {
		$this->activeFindings = [];
		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest( [
				'plugin_status' => PluginPageView::STATUS,
			] ),
		] );
		$GLOBALS[ 'status' ] = 'all';

		( new PluginPageView() )->addCloakedList( [ 'all' => [] ] );

		$this->assertSame( 'all', $GLOBALS[ 'status' ] );
	}

	public function test_the_table_is_left_on_another_list_when_that_is_what_was_asked_for() :void {
		$this->activeFindings = [ $this->standardFinding( 'cloaked/cloaked.php', 'Cloaked Plugin' ) ];
		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest( [
				'plugin_status' => 'inactive',
			] ),
		] );
		$GLOBALS[ 'status' ] = 'inactive';

		( new PluginPageView() )->addCloakedList( [ 'all' => [] ] );

		$this->assertSame( 'inactive', $GLOBALS[ 'status' ] );
	}

	/**
	 * A search from the cloaked tab is a search, as it is from any other tab.
	 */
	public function test_a_search_is_left_as_wordpress_has_it() :void {
		$this->activeFindings = [ $this->standardFinding( 'cloaked/cloaked.php', 'Cloaked Plugin' ) ];
		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest( [
				'plugin_status' => PluginPageView::STATUS,
				's'             => 'something',
			] ),
		] );
		$GLOBALS[ 'status' ] = 'search';

		( new PluginPageView() )->addCloakedList( [ 'all' => [], 'search' => [] ] );

		$this->assertSame( 'search', $GLOBALS[ 'status' ] );
	}

	public function test_must_use_actions_are_stripped_only_on_cloaked_status() :void {
		$mustUse = $this->mustUseFinding( 'mu-loader.php', 'MU Loader' );
		$this->activeFindings = [ $mustUse ];
		$actions = [ 'deactivate' => '<a>Deactivate</a>' ];

		$onAnotherList = new PluginPageView();
		$onAnotherList->addCloakedList( [ 'all' => [] ] );
		$this->assertSame( $actions, $onAnotherList->filterActionLinks( $actions, $mustUse->entry->file ) );
		$this->assertSame( [], $this->viewShowingTheCloakedList()->filterActionLinks( $actions, $mustUse->entry->file ) );
	}

	/**
	 * The page view as it stands once WordPress has chosen the rows of a table that was asked for the cloaked list.
	 */
	private function viewShowingTheCloakedList() :PluginPageView {
		ServicesState::mergeItems( [
			'service_request' => new UnitTestRequest( [
				'plugin_status' => PluginPageView::STATUS,
			] ),
		] );
		$GLOBALS[ 'status' ] = 'all';
		$view = new PluginPageView();
		$view->addCloakedList( [ 'all' => [] ] );
		return $view;
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

	public function ignoredFindings() :array {
		return $this->ignoredFindings;
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
						'all'        => \array_merge( $this->test->activeFindings(), $this->test->ignoredFindings() ),
						'active'     => $this->test->activeFindings(),
						'ignored'    => $this->test->ignoredFindings(),
						'new_active' => [],
					];
				}
			},
		];
		PluginControllerInstaller::install( $controller );
	}
}
