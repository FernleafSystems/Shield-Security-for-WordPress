<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageInvestigateByCore;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\InvokesNonPublicMethods;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class PageInvestigateByCoreBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'ABSPATH' ) ) {
			\define( 'ABSPATH', '/var/www/html/' );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_normalize_path' )->alias( static fn( string $path ) :string => $path );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_render_data_contains_core_subject_tabs_and_table_contracts() :void {
		$page = new PageInvestigateByCoreUnitTestDouble( '6.5.2', false, 4, 7 );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$vars = $renderData[ 'vars' ] ?? [];
		$tables = $vars[ 'tables' ] ?? [];

		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'has_subject' ] ?? false ) );
		$this->assertArrayNotHasKey( 'subject', $vars );
		$this->assertArrayNotHasKey( 'summary', $vars );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame(
			[ 'WordPress Version', 'Core Update Status', 'Install Directory' ],
			\array_column( $vars[ 'overview_rows' ] ?? [], 'label' )
		);
		$this->assertSame( 'File Scan Status', (string)( $vars[ 'tabs' ][ 'file_status' ][ 'label' ] ?? '' ) );
		$this->assertSame( 'File Scan Status', (string)( $tables[ 'file_status' ][ 'title' ] ?? '' ) );
		$this->assertSame( 'Full Scan Results', (string)( $tables[ 'file_status' ][ 'full_log_text' ] ?? '' ) );
		$this->assertTrue( (bool)( $tables[ 'file_status' ][ 'is_flat' ] ?? false ) );

		$this->assertSame( 'file_scan_results', (string)( $tables[ 'file_status' ][ 'table_type' ] ?? '' ) );
		$this->assertSame( 'activity', (string)( $tables[ 'activity' ][ 'table_type' ] ?? '' ) );
		$this->assertFalse( (bool)( $tables[ 'file_status' ][ 'is_empty' ] ?? true ) );
		$this->assertFalse( (bool)( $tables[ 'activity' ][ 'is_empty' ] ?? true ) );
		$this->assertSame( 'core', (string)( $tables[ 'file_status' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( 'core', (string)( $tables[ 'activity' ][ 'subject_type' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'vulnerabilities', $vars[ 'tabs' ] ?? [] );
	}

	public function test_zero_counts_render_empty_state_table_contracts() :void {
		$page = new PageInvestigateByCoreUnitTestDouble( '6.5.2', false, 0, 0 );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$tables = $renderData[ 'vars' ][ 'tables' ] ?? [];

		$this->assertTrue( (bool)( $tables[ 'file_status' ][ 'is_empty' ] ?? false ) );
		$this->assertTrue( (bool)( $tables[ 'activity' ][ 'is_empty' ] ?? false ) );
		$this->assertSame(
			'No file scan status records were found for this subject.',
			(string)( $tables[ 'file_status' ][ 'empty_text' ] ?? '' )
		);
		$this->assertSame(
			'No activity records were found for this subject.',
			(string)( $tables[ 'activity' ][ 'empty_text' ] ?? '' )
		);
		$this->assertArrayNotHasKey( 'table_type', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'table_type', $tables[ 'activity' ] ?? [] );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function adminTopNav( string $nav, string $subnav = '' ) :string {
				return '/admin/'.$nav.'/'.$subnav;
			}

			public function investigateByCore() :string {
				return '/admin/activity/by_core';
			}
		};
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};
		PluginControllerInstaller::install( $controller );
	}

}

class PageInvestigateByCoreUnitTestDouble extends PageInvestigateByCore {

	private string $coreVersion;

	private bool $hasCoreUpdate;

	private int $coreFileIssueCount;

	private int $activityCount;

	public function __construct( string $coreVersion, bool $hasCoreUpdate, int $coreFileIssueCount, int $activityCount ) {
		$this->coreVersion = $coreVersion;
		$this->hasCoreUpdate = $hasCoreUpdate;
		$this->coreFileIssueCount = $coreFileIssueCount;
		$this->activityCount = $activityCount;
	}

	protected function getCoreVersion() :string {
		return $this->coreVersion;
	}

	protected function hasCoreUpdate() :bool {
		return $this->hasCoreUpdate;
	}

	protected function getCoreFileIssueCount() :int {
		return $this->coreFileIssueCount;
	}

	protected function countActivityForSubject( string $subjectType, string $subjectId ) :int {
		return $this->activityCount;
	}

	protected function buildAssetTables( string $subjectType, string $subjectId, string $activitySearchToken ) :array {
		return [
			'file_status' => [
				'title'        => 'File Scan Status',
				'table_type'   => 'file_scan_results',
				'subject_type' => $subjectType,
				'subject_id'   => $subjectId,
				'full_log_text' => 'Full Scan Results',
				'full_log_button_class' => 'btn btn-primary btn-sm',
				'is_flat' => true,
			],
			'activity'    => [
				'table_type'   => 'activity',
				'subject_type' => $subjectType,
				'subject_id'   => $subjectId,
			],
		];
	}
}


