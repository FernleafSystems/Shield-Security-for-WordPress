<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	CloakedPluginIgnore,
	CloakedPluginUnignore
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\CloakedPluginsQueueIssueProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakReason,
	PluginEntry,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestRequest,
	UnitTestUsers
};

class CloakedPluginsQueueIssueProviderTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map( 'rawurlencode', $value );
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\http_build_query( $params );
			}
		);
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpgeneral' => new UnitTestGeneral(),
			'service_request'   => new UnitTestRequest(),
			'service_wpusers'   => new UnitTestUsers( 7 ),
		] );
		UnitTestControllerFactory::install();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_active_standard_plugin_is_reported_across_actions_queue_contracts() :void {
		$provider = new CloakedPluginsQueueIssueProviderTestDouble(
			[
				$this->finding(
					new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'Cloaked Plugin', '1.2.3', '/plugins/cloaked/cloaked.php' ),
					[ CloakReason::AllPlugins ],
					true
				),
			],
			'/deactivate-cloaked'
		);

		$attentionItems = $provider->attentionItems();
		$assessmentRows = $provider->assessmentRows();
		$pane = $provider->railPaneData();

		$this->assertCount( 1, $attentionItems );
		$attentionItem = $attentionItems[ 0 ];
		$this->assertSame( 'hidden_plugins', $attentionItem[ 'key' ] );
		$this->assertSame( 'scans', $attentionItem[ 'zone' ] );
		$this->assertSame( 'security_check', $attentionItem[ 'source' ] );
		$this->assertSame( 'critical', $attentionItem[ 'severity' ] );
		$this->assertSame( 1, $attentionItem[ 'count' ] );
		$this->assertSame( 0, $attentionItem[ 'ignored_count' ] );
		$this->assertTrue( $attentionItem[ 'supports_sub_items' ] );
		$this->assertSame( '/admin/scans/overview?zone=scans', $attentionItem[ 'href' ] );

		$assessmentRow = $assessmentRows[ 0 ];
		$this->assertSame( 'critical', $assessmentRow[ 'status' ] );
		$this->assertSame( 'critical', $assessmentRow[ 'drill_bucket' ] );
		$this->assertSame( 'bi bi-eye-slash-fill', $assessmentRow[ 'item_icon_class' ] );

		$this->assertSame( 'hidden_plugins', $pane[ 'key' ] );
		$this->assertSame( 'critical', $pane[ 'status' ] );
		$this->assertSame( 1, $pane[ 'count_items' ] );
		$this->assertTrue( $pane[ 'is_loaded' ] );
		$this->assertFalse( $pane[ 'is_disabled' ] );
		$this->assertSame( [], $pane[ 'render_action' ] );
		$this->assertCount( 1, $pane[ 'items' ] );
		$row = $pane[ 'items' ][ 0 ];
		$this->assertSame( 'Cloaked Plugin', $row[ 'title' ] );
		$this->assertSame( 'critical', $row[ 'status' ] );
		$this->assertFalse( $row[ 'expandable' ] );
		$this->assertFalse( $row[ 'show_gear' ] );
		$this->assertSame( [], $row[ 'expansion' ] );
		$this->assertSame( '/deactivate-cloaked', $row[ 'actions' ][ 0 ][ 'href' ] );
		$this->assertSame( 'deactivate', $row[ 'actions' ][ 0 ][ 'type' ] );
		$this->assertTrue( $row[ 'actions' ][ 1 ][ 'is_action' ] );
		$this->assertSame( CloakedPluginIgnore::SLUG, $this->decodeAction( $row[ 'actions' ][ 1 ] )[ 'ex' ] ?? '' );
		$this->assertSame( '1', $row[ 'actions' ][ 1 ][ 'attributes' ][ 'data-operator-context-action-ajax' ] );
		$this->assertStringContainsString( '/plugins/cloaked/cloaked.php', \implode( "\n", $row[ 'explanations' ] ) );
	}

	public function test_clear_state_is_good_without_attention_item() :void {
		$provider = new CloakedPluginsQueueIssueProviderTestDouble( [] );

		$this->assertSame( [], $provider->attentionItems() );
		$assessmentRow = $provider->assessmentRows()[ 0 ];
		$this->assertSame( 'good', $assessmentRow[ 'status' ] );
		$this->assertSame( 'critical', $assessmentRow[ 'drill_bucket' ] );

		$pane = $provider->railPaneData();
		$this->assertSame( 'good', $pane[ 'status' ] );
		$this->assertSame( 0, $pane[ 'count_items' ] );
		$this->assertSame( [], $pane[ 'items' ] );
	}

	public function test_inactive_standard_plugin_uses_plugins_management_link() :void {
		$provider = new CloakedPluginsQueueIssueProviderTestDouble(
			[
				$this->finding(
					new PluginEntry( PluginType::Standard, 'quiet/quiet.php', 'Quiet Plugin', '2.0.0', '/plugins/quiet/quiet.php' ),
					[ CloakReason::PluginsList ],
					false
				),
			],
			'',
			'/plugins-search'
		);

		$row = $provider->railPaneData()[ 'items' ][ 0 ];

		$this->assertSame( '/plugins-search', $row[ 'actions' ][ 0 ][ 'href' ] );
		$this->assertSame( 'navigate', $row[ 'actions' ][ 0 ][ 'type' ] );
		$this->assertSame( CloakedPluginIgnore::SLUG, $this->decodeAction( $row[ 'actions' ][ 1 ] )[ 'ex' ] ?? '' );
		$this->assertStringContainsString( 'Final Plugins List', \implode( "\n", $row[ 'explanations' ] ) );
	}

	public function test_must_use_plugin_has_manual_remediation_with_ignore_action() :void {
		$provider = new CloakedPluginsQueueIssueProviderTestDouble(
			[
				$this->finding(
					new PluginEntry( PluginType::MustUse, 'loader.php', 'Loader', '', '/mu-plugins/loader.php' ),
					[ CloakReason::ShowAdvancedPlugins ],
					true,
					false
				),
			]
		);

		$row = $provider->railPaneData()[ 'items' ][ 0 ];

		$this->assertCount( 1, $row[ 'actions' ] );
		$this->assertSame( CloakedPluginIgnore::SLUG, $this->decodeAction( $row[ 'actions' ][ 0 ] )[ 'ex' ] ?? '' );
		$this->assertStringContainsString( '/mu-plugins/loader.php', \implode( "\n", $row[ 'explanations' ] ) );
		$this->assertStringContainsString( 'manual', \strtolower( \implode( "\n", $row[ 'explanations' ] ) ) );
	}

	public function test_ignored_plugin_is_shown_in_detail_without_active_attention_item() :void {
		$ignoredFinding = $this->finding(
			new PluginEntry( PluginType::Standard, 'quiet/quiet.php', 'Quiet Plugin', '2.0.0', '/plugins/quiet/quiet.php' ),
			[ CloakReason::PluginsList ],
			false
		);
		$provider = new CloakedPluginsQueueIssueProviderTestDouble(
			[],
			'',
			'',
			[ $ignoredFinding ]
		);

		$this->assertSame( [], $provider->attentionItems() );

		$pane = $provider->railPaneData();
		$this->assertSame( 'good', $pane[ 'status' ] );
		$this->assertSame( 0, $pane[ 'count_items' ] );
		$this->assertCount( 1, $pane[ 'items' ] );

		$row = $pane[ 'items' ][ 0 ];
		$this->assertSame( 'good', $row[ 'status' ] );
		$this->assertSame( 'Ignored', $row[ 'status_label' ] );
		$this->assertSame( CloakedPluginUnignore::SLUG, $this->decodeAction( $row[ 'actions' ][ 0 ] )[ 'ex' ] ?? '' );
		$this->assertSame( $ignoredFinding->identityKey(), $this->decodeAction( $row[ 'actions' ][ 0 ] )[ 'finding_id' ] ?? '' );
	}

	private function finding(
		PluginEntry $entry,
		array $cloakReasons,
		bool $active,
		bool $networkActive = false
	) :CloakedPluginFinding {
		return new CloakedPluginFinding( $entry, $cloakReasons, $active, $networkActive, 1700000000 );
	}

	private function decodeAction( array $action ) :array {
		$decoded = \json_decode( $action[ 'attributes' ][ 'data-operator-context-action-json' ] ?? '', true );
		return \is_array( $decoded ) ? $decoded : [];
	}
}

class CloakedPluginsQueueIssueProviderTestDouble extends CloakedPluginsQueueIssueProvider {

	private array $activeFindings;
	private array $ignoredFindings;
	private string $deactivateUrl;
	private string $pluginsSearchUrl;

	public function __construct(
		array $activeFindings,
		string $deactivateUrl = '',
		string $pluginsSearchUrl = '',
		array $ignoredFindings = []
	) {
		$this->activeFindings = $activeFindings;
		$this->ignoredFindings = $ignoredFindings;
		$this->deactivateUrl = $deactivateUrl;
		$this->pluginsSearchUrl = $pluginsSearchUrl;
	}

	protected function state() :array {
		return [
			'all'               => \array_merge( $this->activeFindings, $this->ignoredFindings ),
			'active'            => $this->activeFindings,
			'ignored'           => $this->ignoredFindings,
			'system_suppressed' => [],
			'new_active'        => [],
		];
	}

	protected function deactivateUrl( string $pluginFile ) :string {
		return $this->deactivateUrl;
	}

	protected function pluginsSearchUrl( string $pluginFile ) :string {
		return $this->pluginsSearchUrl;
	}
}
