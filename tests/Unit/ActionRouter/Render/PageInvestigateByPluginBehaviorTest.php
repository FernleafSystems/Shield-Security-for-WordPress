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
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
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
use FernleafSystems\Wordpress\Services\Core\Plugins;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class PageInvestigateByPluginBehaviorTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'sanitize_text_field' )->alias( static fn( $text ) => \is_string( $text ) ? \trim( $text ) : '' );
		Functions\when( 'sanitize_key' )->alias( static fn( $text ) => \is_string( $text ) ? \strtolower( \trim( $text ) ) : '' );
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = '' ) :string => \hash( 'sha256', $scheme.'|'.$data )
		);
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
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
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertSame( [], $renderData[ 'vars' ][ 'tables' ] ?? [] );
		$this->assertSame(
			[
				'page'    => 'icwp-wpsf-plugin',
				'nav'     => PluginNavs::NAV_ACTIVITY,
				'nav_sub' => PluginNavs::SUBNAV_ACTIVITY_BY_PLUGIN,
			],
			$renderData[ 'vars' ][ 'lookup_route' ] ?? []
		);
		$this->assertSame(
			[
				'panel_form'            => true,
				'use_select2'           => true,
				'auto_submit_on_change' => true,
			],
			$renderData[ 'vars' ][ 'lookup_behavior' ] ?? []
		);
		$this->assertSame( [], $renderData[ 'vars' ][ 'lookup_ajax' ] ?? null );
		$this->assertSame( '', (string)( $renderData[ 'vars' ][ 'lookup_ajax_attr' ] ?? 'missing' ) );
		$this->assertSame( [], $renderData[ 'vars' ][ 'lookup_shortcuts' ] ?? null );
		$this->assertSame( '', (string)( $renderData[ 'vars' ][ 'offcanvas_history_mode' ] ?? 'missing' ) );
		$this->assertSame(
			[
				[
					'value' => 'akismet/akismet.php',
					'label' => 'Akismet (5.0)',
				],
			],
			$renderData[ 'vars' ][ 'plugin_options' ] ?? []
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
					'author_url' => '',
					'dir' => '/wp-content/plugins/akismet',
					'installed_at' => '2026-02-27',
				],
				'flags' => [
					'is_active' => true,
					'has_update' => false,
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
		$this->assertSame( 'plugin', (string)( $tables[ 'activity' ][ 'subject_type' ] ?? '' ) );
		$this->assertSame( 'akismet/akismet.php', (string)( $tables[ 'activity' ][ 'subject_id' ] ?? '' ) );
		$this->assertSame( 'activity', (string)( $tables[ 'activity' ][ 'table_type' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'file_status' ][ 'table_id' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'file_status' ][ 'datatables_init_attr' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'activity' ][ 'datatables_init_attr' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'file_status' ][ 'table_action_attr' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $tables[ 'activity' ][ 'table_action_attr' ] ?? '' ) );
		$this->assertFalse( (bool)( $tables[ 'file_status' ][ 'is_empty' ] ?? true ) );
		$this->assertFalse( (bool)( $tables[ 'activity' ][ 'is_empty' ] ?? true ) );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'hrefs' ] ?? [] );
		$this->assertArrayNotHasKey( 'back_to_investigate', $renderData[ 'strings' ] ?? [] );
		$this->assertArrayNotHasKey( 'subject', $vars );
		$this->assertArrayNotHasKey( 'summary', $vars );
		$this->assertSame( 'File Scan Status', (string)( $vars[ 'tabs' ][ 'file_status' ][ 'label' ] ?? '' ) );
		$this->assertSame( 'File Scan Status', (string)( $tables[ 'file_status' ][ 'title' ] ?? '' ) );
		$this->assertFalse( (bool)( $tables[ 'file_status' ][ 'show_header' ] ?? true ) );
		$this->assertFalse( (bool)( $tables[ 'activity' ][ 'show_header' ] ?? true ) );
		$this->assertArrayNotHasKey( 'full_log_href', $tables[ 'activity' ] ?? [] );
		$this->assertNotSame( '', (string)( $tables[ 'file_status' ][ 'render_item_analysis_attr' ] ?? '' ) );
		$fileStatusAction = $this->decodeJsonAttr( (string)( $tables[ 'file_status' ][ 'table_action_attr' ] ?? '' ) );
		$this->assertSame( 'plugin', $fileStatusAction[ 'type' ] ?? '' );
		$this->assertSame( 'akismet/akismet.php', $fileStatusAction[ 'file' ] ?? '' );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$fileStatusAction[ 'results_display_options' ] ?? []
		);
		$this->assertTrue( (bool)( $tables[ 'file_status' ][ 'is_flat' ] ?? false ) );
		$this->assertSame(
			[ 'Name', 'Slug', 'Version', 'Author', 'File', 'Install Directory', 'Installed', 'Active Status', 'Update Available Status', 'Vulnerability Status' ],
			\array_column( $vars[ 'overview_rows' ] ?? [], 'label' )
		);
		$this->assertSame( 2, (int)( $vars[ 'vulnerabilities' ][ 'count' ] ?? 0 ) );
		$this->assertSame( 'Known Vulnerabilities', (string)( $vars[ 'vulnerabilities' ][ 'title' ] ?? '' ) );
		$this->assertArrayHasKey( 'vulnerabilities', $vars[ 'tabs' ] ?? [] );
		$this->assertNotSame( '', (string)( $vars[ 'subject_header' ][ 'context_step_json' ] ?? '' ) );
	}

	public function test_valid_lookup_includes_reinstall_context_action_for_wporg_plugin() :void {
		$this->installServices(
			[ 'plugin_slug' => 'akismet/akismet.php' ],
			[
				'akismet/akismet.php' => new PageInvestigateByPluginTestPluginVo( 'akismet/akismet.php', true ),
			]
		);
		$page = new PageInvestigateByPluginUnitTestDouble(
			(object)[ 'file' => 'akismet/akismet.php' ],
			[
				'info'  => [
					'name'    => 'Akismet',
					'slug'    => 'akismet',
					'file'    => 'akismet/akismet.php',
					'version' => '5.0',
					'author'  => 'Automattic',
					'author_url' => '',
					'dir' => '/wp-content/plugins/akismet',
					'installed_at' => '2026-02-27',
				],
				'flags' => [
					'is_active' => true,
					'has_update' => false,
				],
				'hrefs' => [
					'vul_info' => '',
				],
				'vars'  => [
					'count_items' => 0,
				],
			]
		);

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$contextStep = $this->decodeJsonAttr( (string)( $renderData[ 'vars' ][ 'subject_header' ][ 'context_step_json' ] ?? '' ) );
		$actions = $contextStep[ 'actions' ] ?? [];

		$this->assertCount( 1, $actions );
		$this->assertSame( 'update', $actions[ 0 ][ 'type' ] ?? '' );
		$this->assertNotEmpty( $actions[ 0 ][ 'label' ] ?? '' );

		$actionData = $this->decodeJsonAttr( (string)( $actions[ 0 ][ 'ajax_action_json' ] ?? '' ) );
		$this->assertSame( 'plugin_reinstall', $actionData[ 'ex' ] ?? '' );
		$this->assertSame( 'akismet/akismet.php', $actionData[ 'file' ] ?? '' );
		$this->assertArrayNotHasKey( 'reinstall', $actionData );
	}

	public function test_valid_lookup_omits_reinstall_context_action_for_non_wporg_plugin() :void {
		$this->installServices(
			[ 'plugin_slug' => 'premium/plugin.php' ],
			[
				'premium/plugin.php' => new PageInvestigateByPluginTestPluginVo( 'premium/plugin.php', false ),
			]
		);
		$page = new PageInvestigateByPluginUnitTestDouble(
			(object)[ 'file' => 'premium/plugin.php' ],
			[
				'info'  => [
					'name'    => 'Premium Plugin',
					'slug'    => 'premium',
					'file'    => 'premium/plugin.php',
					'version' => '1.0',
					'author'  => 'Vendor',
					'author_url' => '',
					'dir' => '/wp-content/plugins/premium',
					'installed_at' => '2026-02-27',
				],
				'flags' => [
					'is_active' => true,
					'has_update' => false,
				],
				'hrefs' => [
					'vul_info' => '',
				],
				'vars'  => [
					'count_items' => 0,
				],
			]
		);

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$contextStep = $this->decodeJsonAttr( (string)( $renderData[ 'vars' ][ 'subject_header' ][ 'context_step_json' ] ?? '' ) );

		$this->assertSame( [], $contextStep[ 'actions' ] ?? null );
	}

	public function test_zero_counts_build_empty_table_contracts_and_no_known_vulnerability_title() :void {
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
					'author_url' => '',
					'dir' => '/wp-content/plugins/akismet',
					'installed_at' => '2026-02-27',
				],
				'flags' => [
					'is_active' => false,
					'has_update' => false,
				],
				'hrefs' => [
					'vul_info' => 'https://lookup.example/plugin',
				],
				'vars'  => [
					'count_items' => 0,
				],
			],
			0,
			0,
			0
		);

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );
		$vars = $renderData[ 'vars' ] ?? [];
		$strings = $renderData[ 'strings' ] ?? [];
		$tables = $vars[ 'tables' ] ?? [];

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
		$this->assertArrayNotHasKey( 'table_id', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'datatables_init_attr', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'datatables_init_attr', $tables[ 'activity' ] ?? [] );
		$this->assertArrayNotHasKey( 'table_action_attr', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'table_action_attr', $tables[ 'activity' ] ?? [] );
		$this->assertArrayNotHasKey( 'render_item_analysis_attr', $tables[ 'file_status' ] ?? [] );
		$this->assertArrayNotHasKey( 'subject_type', $tables[ 'activity' ] ?? [] );
		$this->assertArrayNotHasKey( 'subject_id', $tables[ 'activity' ] ?? [] );
		$this->assertSame( '', (string)( $vars[ 'vulnerabilities' ][ 'title' ] ?? 'missing' ) );
		$this->assertSame( '', (string)( $vars[ 'vulnerabilities' ][ 'lookup_href' ] ?? 'missing' ) );
		$this->assertSame( '', (string)( $vars[ 'vulnerabilities' ][ 'lookup_text' ] ?? 'missing' ) );
	}

	public function test_render_data_includes_lookup_helper_string() :void {
		$this->installServices();
		$page = new PageInvestigateByPluginUnitTestDouble( null, [] );

		$renderData = $this->invokeNonPublicMethod( $page, 'getRenderData' );

		$this->assertArrayHasKey( 'lookup_helper', $renderData[ 'strings' ] ?? [] );
		$this->assertNotEmpty( $renderData[ 'strings' ][ 'lookup_helper' ] ?? '' );
	}

	private function installControllerStub() :void {
		UnitTestControllerFactory::install(
			new UnitTestPluginUrls()
		);
	}

	private function installServices( array $query = [], array $pluginVos = [], array $updates = [] ) :void {
		ServicesState::installItems( [
			'service_request'   => new UnitTestRequest( $query ),
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_wpusers'   => new UnitTestUsers(),
			'service_wpplugins' => new PageInvestigateByPluginTestPluginsService( $pluginVos, $updates ),
		] );
	}

	private function decodeJsonAttr( string $json ) :array {
		return $json === '' ? [] : \json_decode( $json, true, 512, \JSON_THROW_ON_ERROR );
	}

}

class PageInvestigateByPluginTestPluginsService extends Plugins {

	private array $pluginVos;

	private array $updates;

	public function __construct( array $pluginVos, array $updates = [] ) {
		$this->pluginVos = $pluginVos;
		$this->updates = $updates;
	}

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		return $this->pluginVos[ $file ] ?? null;
	}

	public function isUpdateAvailable( $file ) :bool {
		return !empty( $this->updates[ $file ] );
	}
}

class PageInvestigateByPluginTestPluginVo extends WpPluginVo {

	public string $file;
	public string $Name;
	public string $Title;

	private bool $isWpOrg;

	public function __construct( string $file, bool $isWpOrg ) {
		$this->file = $file;
		$this->Name = 'Test Plugin';
		$this->Title = 'Test Plugin';
		$this->isWpOrg = $isWpOrg;
	}

	public function __get( string $key ) {
		return $key === 'asset_type' ? 'plugin' : ( $this->{$key} ?? null );
	}

	public function isWpOrg() :bool {
		return $this->isWpOrg;
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
		$hasVulnerabilities = $this->vulnerabilityCount > 0;
		return [
			'count'       => $this->vulnerabilityCount,
			'status'      => $hasVulnerabilities ? 'critical' : 'good',
			'title'       => $hasVulnerabilities ? 'Known Vulnerabilities' : '',
			'summary'     => 'Summary',
			'lookup_href' => $hasVulnerabilities ? $lookupHref : '',
			'lookup_text' => $hasVulnerabilities ? 'Lookup' : '',
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
