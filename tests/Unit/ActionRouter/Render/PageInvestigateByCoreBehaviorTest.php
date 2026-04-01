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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestUsers
};

class PageInvestigateByCoreBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'ABSPATH' ) ) {
			\define( 'ABSPATH', '/var/www/html/' );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = '' ) :string => \hash( 'sha256', $scheme.'|'.$data )
		);
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'wp_normalize_path' )->alias( static fn( string $path ) :string => $path );
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}
				$pieces = [];
				foreach ( $params as $key => $value ) {
					$pieces[] = $key.'='.( \is_array( $value ) ? \rawurlencode( (string)\json_encode( $value ) ) : $value );
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->installControllerStub();
		$this->installServices();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
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
		$this->assertFalse( (bool)( $tables[ 'file_status' ][ 'show_header' ] ?? true ) );
		$this->assertFalse( (bool)( $tables[ 'activity' ][ 'show_header' ] ?? true ) );
		$this->assertArrayNotHasKey( 'full_log_href', $tables[ 'activity' ] ?? [] );
		$this->assertTrue( (bool)( $tables[ 'file_status' ][ 'is_flat' ] ?? false ) );

		$this->assertSame( 'file_scan_results', (string)( $tables[ 'file_status' ][ 'table_type' ] ?? '' ) );
		$this->assertSame( 'activity', (string)( $tables[ 'activity' ][ 'table_type' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'file_status' ][ 'datatables_init_attr' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'activity' ][ 'datatables_init_attr' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'file_status' ][ 'table_action_attr' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'activity' ][ 'table_action_attr' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'file_status' ][ 'scan_results_action_attr' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'file_status' ][ 'render_item_analysis_attr' ] ?? '' ) );
		$this->assertFalse( (bool)( $tables[ 'file_status' ][ 'is_empty' ] ?? true ) );
		$this->assertFalse( (bool)( $tables[ 'activity' ][ 'is_empty' ] ?? true ) );
		$this->assertSame( 'core', (string)( $tables[ 'file_status' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( 'core', (string)( $tables[ 'activity' ][ 'subject_type' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'vulnerabilities', $vars[ 'tabs' ] ?? [] );
	}

	public function test_zero_counts_render_empty_state_table_contracts() :void {
		$page = new PageInvestigateByCoreUnitTestDouble( '6.5.2', false, 0, 0 );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$strings = $renderData[ 'strings' ] ?? [];
		$tables = $renderData[ 'vars' ][ 'tables' ] ?? [];

		$this->assertTrue( (bool)( $tables[ 'file_status' ][ 'is_empty' ] ?? false ) );
		$this->assertTrue( (bool)( $tables[ 'activity' ][ 'is_empty' ] ?? false ) );
		$this->assertSame(
			(string)( $strings[ 'file_status_empty_text' ] ?? '' ),
			(string)( $tables[ 'file_status' ][ 'empty_text' ] ?? '' )
		);
		$this->assertSame(
			(string)( $strings[ 'activity_empty_text' ] ?? '' ),
			(string)( $tables[ 'activity' ][ 'empty_text' ] ?? '' )
		);
		$this->assertSame( 'info', (string)( $tables[ 'file_status' ][ 'empty_status' ] ?? '' ) );
		$this->assertSame( 'info', (string)( $tables[ 'activity' ][ 'empty_status' ] ?? '' ) );
		$this->assertArrayNotHasKey( 'table_type', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'table_type', $tables[ 'activity' ] ?? [] );
		$this->assertArrayNotHasKey( 'datatables_init_attr', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'datatables_init_attr', $tables[ 'activity' ] ?? [] );
		$this->assertArrayNotHasKey( 'table_action_attr', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'table_action_attr', $tables[ 'activity' ] ?? [] );
		$this->assertArrayNotHasKey( 'scan_results_action_attr', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'render_item_analysis_attr', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'subject_type', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'subject_type', $tables[ 'activity' ] ?? [] );
		$this->assertArrayNotHasKey( 'subject_id', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'subject_id', $tables[ 'activity' ] ?? [] );
	}

	private function installControllerStub() :void {
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls()
		);
	}

	private function installServices( array $query = [] ) :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( $query ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers(),
		] );
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
}
