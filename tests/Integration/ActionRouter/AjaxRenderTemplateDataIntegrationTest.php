<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionRoutingController,
	Actions\AjaxRender,
	Actions\Render\Components,
	Actions\Render\PluginAdminPages,
	RoutedResponse,
	Utility\ActionsMap,
	Utility\AjaxRenderTargetPolicy,
	Utility\ExternalEntryGuard
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\PluginGeneral;

/**
 * A panel loaded over AJAX is given its request's data, but only the simple values in it reach the panel's
 * template: a request can't supply the template's own structures. Each panel must therefore render exactly as
 * it does when the same data comes from the plugin's own code, which gives the template all of it.
 */
class AjaxRenderTemplateDataIntegrationTest extends ShieldIntegrationTestCase {

	private const INJECTED = 'injected-by-the-request';

	private const IP = '203.0.113.5';

	/**
	 * @var array<class-string,array<string,mixed>>|null
	 */
	private ?array $pageRequests = null;

	private \Closure $blockHttp;

	private array $optionSnapshot = [];

	private string $lockContentCacheKey = '';

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->pageRequests = null;
		// Some panels look things up over the network. None of that is under test here.
		$this->blockHttp = static fn() => new \WP_Error( 'no_network', 'No network in this test.' );
		\add_filter( 'pre_http_request', $this->blockHttp, 1, 0 );
	}

	public function tear_down() {
		\remove_filter( 'pre_http_request', $this->blockHttp, 1 );
		if ( $this->lockContentCacheKey !== '' ) {
			\wp_cache_delete( $this->lockContentCacheKey, $this->requireController()->prefix( 'filelocker' ) );
			$this->requireController()->comps->file_locker->clearLocks();
			$this->lockContentCacheKey = '';
		}
		if ( !empty( $this->optionSnapshot ) ) {
			$this->restoreSelectedOptions( $this->optionSnapshot );
			$this->optionSnapshot = [];
		}
		parent::tear_down();
	}

	/**
	 * @dataProvider ajaxRenderTargets
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender> $target
	 */
	public function test_panel_renders_the_same_from_a_request_as_from_the_plugins_own_data( string $target ) :void {
		$data = $this->actionDataFor( $target );
		// A first render settles anything a panel records about what it shows.
		$this->renderOverAjax( $target, $data );

		$fromPlugin = $this->outputOf( $this->requireController()->action_router->action( $target, $data ) );
		$fromRequest = $this->outputOf( $this->renderOverAjax( $target, $data ) );

		$this->assertSame( $fromPlugin, $fromRequest );
	}

	/**
	 * @dataProvider ajaxRenderTargets
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender> $target
	 */
	public function test_request_cannot_supply_a_panels_template_structures( string $target ) :void {
		$structures = \array_fill_keys(
			[ 'ajax', 'classes', 'content', 'flags', 'head', 'header', 'hrefs', 'imgs', 'strings', 'vars' ],
			[ self::INJECTED => self::INJECTED, 'title' => self::INJECTED ]
		);

		$response = $this->renderOverAjax( $target, \array_merge( $structures, $this->actionDataFor( $target ) ) );

		$this->outputOf( $response );
		$this->assertStringNotContainsString( self::INJECTED, (string)\wp_json_encode( $response->payload() ) );
	}

	/**
	 * @return list<array{class-string}>
	 */
	public static function ajaxRenderTargets() :array {
		return \array_map(
			static fn( string $slug ) :array => [ ActionsMap::ActionFromRequest( $slug ) ],
			( new AjaxRenderTargetPolicy() )->allowedRenderSlugs()
		);
	}

	private function renderOverAjax( string $target, array $data ) :RoutedResponse {
		return $this->requireController()->action_router->action(
			AjaxRender::SLUG,
			\array_merge( $data, [ 'render_slug' => $target::SLUG ] ),
			ActionRoutingController::ACTION_AJAX
		);
	}

	/**
	 * The HTML of a panel that rendered, with the IDs a render generates for itself taken out. A panel that
	 * rendered nothing, or whose template failed, proves nothing about its data, so it fails here.
	 */
	private function outputOf( RoutedResponse $response ) :string {
		$payload = $response->payload();
		$output = (string)( $payload[ 'render_output' ] ?? $payload[ 'html' ] ?? '' );
		$this->assertNotSame( '', $output, 'The panel rendered nothing.' );
		$this->assertStringNotContainsString( 'Exception during render', $output, 'The panel\'s template failed.' );
		$renderId = $payload[ 'render_data' ][ 'unique_render_id' ] ?? '';
		if ( \is_string( $renderId ) && $renderId !== '' ) {
			$output = \str_replace( $renderId, 'RENDER-ID', $output );
		}
		// Three things inside a panel take a fresh value of their own on every render: the IP analysis tabs, the
		// options form's sections, and the cache-buster on a file download link.
		return (string)\preg_replace(
			[
				'#ipanalyse-[0-9a-f]{13,}#',
				'#\b(tab|tab-navlink|section-title)-[A-Za-z0-9]{6}-#',
				'#\brand=[A-Za-z0-9]{6}\b#',
			],
			[ 'ipanalyse-ID', '$1-ID-', 'rand=ID' ],
			$output
		);
	}

	/**
	 * The data the panel's action is given for its page's request, as an entry point hands it over: with the
	 * request's decoded form alongside. A panel that becomes loadable over AJAX needs its page's request adding
	 * to pageRequests() before it can be tested here.
	 */
	private function actionDataFor( string $target ) :array {
		return ExternalEntryGuard::ActionDataFromTransport( $this->pageRequests()[ $target ] );
	}

	/**
	 * The request each panel's page sends for it, with the records those requests refer to.
	 * @return array<class-string,array<string,mixed>>
	 */
	private function pageRequests() :array {
		return $this->pageRequests ??= $this->buildPageRequests();
	}

	private function buildPageRequests() :array {
		$con = $this->requireController();
		$scanOptions = new PluginAdminPages\ScanResultsDisplayOptions();

		// An IP with history, so its panels show the same thing each time they're rendered.
		TestDataFactory::insertBotSignal( self::IP );

		$pluginFile = $con->base_file;
		$scanItem = TestDataFactory::insertAfsFileScanResultTracked(
			TestDataFactory::insertCompletedScan( 'afs' ),
			TestDataFactory::afsFileItemIdFromPath( WP_PLUGIN_DIR.'/'.$pluginFile ),
			[
				'is_in_plugin' => 1,
				'ptg_slug'     => $pluginFile,
			]
		);
		$lockId = $this->lockedFile();

		return [
			Components\Widgets\DashboardLiveMonitorTicker::class       => [ 'limit' => '12' ],
			Components\Traffic\TrafficLiveLogs::class                  => [ 'limit' => '12' ],
			Components\Widgets\WpDashboardSummary::class               => [],
			Components\Scans\ScansFileLockerDiff::class                => [ 'rid' => (string)$lockId ],
			Components\OffCanvas\IpAnalysis::class                     => [ 'ip' => self::IP ],
			Components\OffCanvas\ImportExportSitesAuthoriseUrls::class => [],
			Components\OffCanvas\IpRuleAddForm::class                  => [],
			Components\Rules\RuleBuilder::class                        => [],
			Components\Rules\RulesManager::class                       => [],
			Components\SuperSearchResults::class                       => [ 'search' => 'login' ],
			Components\OffCanvas\SearchHelp::class                     => [],
			Components\OffCanvas\ZoneComponentConfig::class            => [ 'zone_component_slug' => PluginGeneral::Slug(), 'configure_panel' => 'shield:general' ],
			Components\OffCanvas\FormReportCreate::class               => [],
			Components\UserMfa\ConfigForm::class                       => [],
			PluginAdminPages\ActionsQueueAssetFileStatusDetail::class  => $scanOptions->buildSubjectActionData( 'plugin', $pluginFile ),
			Components\Scans\Results\Wordpress::class                  => $scanOptions->buildDisplayContextActionData(),
			Components\Scans\Results\Plugins::class                    => $scanOptions->buildDisplayContextActionData(),
			Components\Scans\Results\Themes::class                     => $scanOptions->buildDisplayContextActionData(),
			Components\Scans\Results\Malware::class                    => $scanOptions->buildDisplayContextActionData(),
			Components\Scans\Results\FileLocker::class                 => $scanOptions->buildDisplayContextActionData(),
			PluginAdminPages\ConfigureDrillDownDiagnosis::class        => [ 'zone' => 'login', 'include_landing_refresh' => '1' ],
			PluginAdminPages\ConfigureSearchResults::class             => [ 'search' => 'login' ],
			PluginAdminPages\InvestigateByUserPanelBody::class         => [],
			PluginAdminPages\InvestigateByIpPanelBody::class           => [ 'analyse_ip' => self::IP ],
			PluginAdminPages\InvestigateByPluginPanelBody::class       => [],
			PluginAdminPages\InvestigateByThemePanelBody::class        => [],
			PluginAdminPages\InvestigateByCorePanelBody::class         => [],
			PluginAdminPages\TrafficLogLivePanelBody::class            => [],
			Components\Scans\ItemAnalysis\Container::class             => [ 'rid' => (string)$scanItem[ 'result_item_id' ] ],
			Components\Scans\ItemAnalysis\Diff::class                  => [ 'rid' => (string)$scanItem[ 'result_item_id' ] ],
		];
	}

	/**
	 * A locked file that has changed since it was locked, with its original content to hand.
	 * @return int the lock's ID
	 */
	private function lockedFile() :int {
		$con = $this->requireController();
		$this->optionSnapshot = $this->snapshotSelectedOptions( [ 'file_locker', 'filelocker_state', 'snapi_data' ] );
		$this->enablePremiumCapabilities( [ 'scan_file_locker' ] );
		RuntimeTestState::primeShieldNetHandshake();
		$con->opts->optSet( 'file_locker', [ 'wpconfig' ] )->store();
		$this->requireTransactionScopedDb( 'file_locker' );

		$lockId = TestDataFactory::insertFileLockRecord( 'wpconfig', ABSPATH.'wp-load.php', \time() - 60 );
		$con->comps->file_locker->clearLocks();
		$this->lockContentCacheKey = 'file-content-'.$lockId;
		\wp_cache_set( $this->lockContentCacheKey, 'the original content', $con->prefix( 'filelocker' ), 60 );
		return $lockId;
	}
}
