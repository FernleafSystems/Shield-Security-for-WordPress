<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\HiddenPluginsQueueIssueProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins\{
	HiddenPluginFinding,
	HiddenReason,
	PluginEntry,
	PluginType
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class HiddenPluginsQueueIssueProviderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		UnitTestControllerFactory::install();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_active_standard_plugin_is_reported_across_actions_queue_contracts() :void {
		$provider = new HiddenPluginsQueueIssueProviderTestDouble(
			[
				$this->finding(
					new PluginEntry( PluginType::Standard, 'hidden/hidden.php', 'Hidden Plugin', '1.2.3', '/plugins/hidden/hidden.php' ),
					[ HiddenReason::AllPlugins ],
					true
				),
			],
			'/deactivate-hidden'
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
		$this->assertFalse( $attentionItem[ 'supports_sub_items' ] );
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
		$this->assertSame( 'Hidden Plugin', $row[ 'title' ] );
		$this->assertSame( 'critical', $row[ 'status' ] );
		$this->assertFalse( $row[ 'expandable' ] );
		$this->assertFalse( $row[ 'show_gear' ] );
		$this->assertSame( [], $row[ 'expansion' ] );
		$this->assertSame( '/deactivate-hidden', $row[ 'actions' ][ 0 ][ 'href' ] );
		$this->assertSame( 'deactivate', $row[ 'actions' ][ 0 ][ 'type' ] );
		$this->assertStringContainsString( '/plugins/hidden/hidden.php', \implode( "\n", $row[ 'explanations' ] ) );
	}

	public function test_clear_state_is_good_without_attention_item() :void {
		$provider = new HiddenPluginsQueueIssueProviderTestDouble( [] );

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
		$provider = new HiddenPluginsQueueIssueProviderTestDouble(
			[
				$this->finding(
					new PluginEntry( PluginType::Standard, 'quiet/quiet.php', 'Quiet Plugin', '2.0.0', '/plugins/quiet/quiet.php' ),
					[ HiddenReason::PluginsList ],
					false
				),
			],
			'',
			'/plugins-search'
		);

		$row = $provider->railPaneData()[ 'items' ][ 0 ];

		$this->assertSame( '/plugins-search', $row[ 'actions' ][ 0 ][ 'href' ] );
		$this->assertSame( 'navigate', $row[ 'actions' ][ 0 ][ 'type' ] );
		$this->assertStringContainsString( 'Final Plugins List', \implode( "\n", $row[ 'explanations' ] ) );
	}

	public function test_must_use_plugin_has_manual_remediation_without_action_link() :void {
		$provider = new HiddenPluginsQueueIssueProviderTestDouble(
			[
				$this->finding(
					new PluginEntry( PluginType::MustUse, 'loader.php', 'Loader', '', '/mu-plugins/loader.php' ),
					[ HiddenReason::ShowAdvancedPlugins ],
					true,
					false
				),
			]
		);

		$row = $provider->railPaneData()[ 'items' ][ 0 ];

		$this->assertSame( [], $row[ 'actions' ] );
		$this->assertStringContainsString( '/mu-plugins/loader.php', \implode( "\n", $row[ 'explanations' ] ) );
		$this->assertStringContainsString( 'manual', \strtolower( \implode( "\n", $row[ 'explanations' ] ) ) );
	}

	private function finding(
		PluginEntry $entry,
		array $hiddenReasons,
		bool $active,
		bool $networkActive = false
	) :HiddenPluginFinding {
		return new HiddenPluginFinding( $entry, $hiddenReasons, $active, $networkActive, 1700000000 );
	}
}

class HiddenPluginsQueueIssueProviderTestDouble extends HiddenPluginsQueueIssueProvider {

	private array $findings;
	private string $deactivateUrl;
	private string $pluginsSearchUrl;

	public function __construct( array $findings, string $deactivateUrl = '', string $pluginsSearchUrl = '' ) {
		$this->findings = $findings;
		$this->deactivateUrl = $deactivateUrl;
		$this->pluginsSearchUrl = $pluginsSearchUrl;
	}

	protected function findings() :array {
		return $this->findings;
	}

	protected function deactivateUrl( string $pluginFile ) :string {
		return $this->deactivateUrl;
	}

	protected function pluginsSearchUrl( string $pluginFile ) :string {
		return $this->pluginsSearchUrl;
	}
}
