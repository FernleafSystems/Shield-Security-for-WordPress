<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageInvestigateByPlugin;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Request;

class PageInvestigateByPluginBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $text ) => \is_string( $text ) ? \trim( $text ) : '' );
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );

		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_empty_lookup_returns_empty_state() :void {
		$this->installServices();
		$page = new PageInvestigateByPluginUnitTestDouble( null, [] );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_lookup' ] ?? true ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? true ) );
		$this->assertSame( [], $renderData[ 'vars' ][ 'tables' ] ?? [] );
		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
			],
			$renderData[ 'vars' ][ 'lookup_route' ] ?? []
		);
	}

	public function test_invalid_lookup_sets_subject_not_found_flag() :void {
		$this->installServices( [ 'plugin_slug' => 'missing/plugin.php' ] );
		$page = new PageInvestigateByPluginUnitTestDouble( null, [] );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_lookup' ] ?? false ) );
		$this->assertFalse( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? true ) );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'subject_not_found' ] ?? false ) );
	}

	public function test_valid_lookup_builds_expected_table_contract_payload() :void {
		$this->installServices( [ 'plugin_slug' => 'akismet/akismet.php' ] );
		$subject = (object)[
			'file' => 'akismet/akismet.php',
		];
		$page = new PageInvestigateByPluginUnitTestDouble(
			$subject,
			[
				'info'  => [
					'name'    => 'Akismet',
					'slug'    => 'akismet',
					'file'    => 'akismet/akismet.php',
					'version' => '5.0',
					'author'  => 'Automattic',
				],
				'flags' => [
					'is_active' => true,
				],
				'hrefs' => [
					'vul_info' => 'https://lookup.example/plugin',
				],
				'vars'  => [
					'count_items' => 3,
				],
			],
			4,
			7,
			2
		);

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$vars = $renderData[ 'vars' ] ?? [];
		$tables = $vars[ 'tables' ] ?? [];

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? false ) );
		$this->assertSame( 'plugin', (string)( $tables[ 'file_status' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( 'plugin', (string)( $tables[ 'activity' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( 'akismet/akismet.php', (string)( $tables[ 'file_status' ][ 'subject_id' ] ?? '' ) );
		$this->assertSame( 'akismet/akismet.php', (string)( $tables[ 'activity' ][ 'subject_id' ] ?? '' ) );
		$this->assertSame( 'file_scan_results', (string)( $tables[ 'file_status' ][ 'table_type' ] ?? '' ) );
		$this->assertSame( 'activity', (string)( $tables[ 'activity' ][ 'table_type' ] ?? '' ) );
		$this->assertSame( 'good', (string)( $vars[ 'subject' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'good', (string)( $vars[ 'subject' ][ 'status_pills' ][ 0 ][ 'status' ] ?? '' ) );
		$this->assertSame( 'critical', (string)( $vars[ 'summary' ][ 'vulnerabilities' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'warning', (string)( $vars[ 'summary' ][ 'file_status' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'warning', (string)( $vars[ 'summary' ][ 'activity' ][ 'status' ] ?? '' ) );
		$this->assertSame( 'warning', (string)( $vars[ 'summary' ][ 'issues' ][ 'status' ] ?? '' ) );
		$this->assertSame( 2, (int)( $vars[ 'vulnerabilities' ][ 'count' ] ?? 0 ) );
		$this->assertArrayHasKey( 'vulnerabilities', $vars[ 'tabs' ] ?? [] );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function rootAdminPageSlug() :string {
				return 'icwp-wpsf-plugin';
			}

			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function investigateByPlugin( string $slug = '' ) :string {
				return empty( $slug ) ? '/admin/activity/by_plugin' : '/admin/activity/by_plugin?plugin_slug='.$slug;
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		PluginControllerInstaller::install( $controller );
	}

	private function installServices( array $query = [] ) :void {
		ServicesState::installItems( [
			'service_request' => new class( $query ) extends Request {
				private array $queryValues;

				public function __construct( array $queryValues = [] ) {
					$this->queryValues = $queryValues;
				}

				public function query( $key, $default = null ) {
					return $this->queryValues[ $key ] ?? $default;
				}
			},
		] );
	}

}

class PageInvestigateByPluginUnitTestDouble extends PageInvestigateByPlugin {

	private $subject;

	private array $assetData;

	private int $fileStatusCount;

	private int $activityCount;

	private int $vulnerabilityCount;

	public function __construct( $subject, array $assetData, int $fileStatusCount = 0, int $activityCount = 0, int $vulnerabilityCount = 0 ) {
		$this->subject = $subject;
		$this->assetData = $assetData;
		$this->fileStatusCount = $fileStatusCount;
		$this->activityCount = $activityCount;
		$this->vulnerabilityCount = $vulnerabilityCount;
	}

	protected function resolveSubject( string $lookup ) {
		return empty( $lookup ) ? null : $this->subject;
	}

	protected function buildSubjectAssetData( $subject ) :array {
		return $this->assetData;
	}

	protected function countFileScanResultsForSubject( string $subjectType, string $subjectId ) :int {
		return $this->fileStatusCount;
	}

	protected function countActivityForSubject( string $subjectType, string $subjectId ) :int {
		return $this->activityCount;
	}

	protected function buildVulnerabilityData( string $subjectId, string $lookupHref ) :array {
		return [
			'count'       => $this->vulnerabilityCount,
			'status'      => $this->vulnerabilityCount > 0 ? 'critical' : 'good',
			'title'       => 'Known Vulnerabilities',
			'summary'     => 'Summary',
			'lookup_href' => $lookupHref,
			'lookup_text' => 'Lookup',
		];
	}

	protected function buildAssetTables( string $subjectType, string $subjectId, string $activitySearchToken ) :array {
		return [
			'file_status' => [
				'table_type'   => 'file_scan_results',
				'subject_type' => $subjectType,
				'subject_id'   => $subjectId,
			],
			'activity'    => [
				'table_type'   => 'activity',
				'subject_type' => $subjectType,
				'subject_id'   => $subjectId,
			],
		];
	}

	protected function buildPluginLookupOptions() :array {
		return [
			[
				'value' => 'akismet/akismet.php',
				'label' => 'Akismet (5.0)',
			],
		];
	}
}
