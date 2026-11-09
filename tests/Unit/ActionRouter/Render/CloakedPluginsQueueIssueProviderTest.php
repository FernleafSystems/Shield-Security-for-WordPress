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
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\MU\MUHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory,
	UnitTestGeneral,
	UnitTestRequest,
	UnitTestUsers
};
use FernleafSystems\Wordpress\Services\Core\Fs;

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
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( string $path ) :string => \str_replace( '\\', '/', $path )
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
			'service_wpfs'      => new Fs(),
		] );
		$this->installCapability( true );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_active_standard_plugin_is_reported_across_actions_queue_contracts() :void {
		$finding = $this->finding(
			new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'Cloaked Plugin', '1.2.3', '/plugins/cloaked/cloaked.php' ),
			[ CloakReason::AllPlugins ],
			true
		);
		$provider = new CloakedPluginsQueueIssueProviderTestDouble( [ $finding ], '/deactivate-cloaked' );

		$attentionItem = $provider->attentionItems()[ 0 ];
		$this->assertSame( 'hidden_plugins', $attentionItem[ 'key' ] );
		$this->assertSame( 'scans', $attentionItem[ 'zone' ] );
		$this->assertSame( 'security_check', $attentionItem[ 'source' ] );
		$this->assertSame( 'critical', $attentionItem[ 'severity' ] );
		$this->assertSame( 1, $attentionItem[ 'count' ] );
		$this->assertSame( 0, $attentionItem[ 'ignored_count' ] );
		$this->assertTrue( $attentionItem[ 'supports_sub_items' ] );
		$this->assertSame( '/admin/scans/overview?zone=scans', $attentionItem[ 'href' ] );

		$assessmentRow = $provider->assessmentRows()[ 0 ];
		$this->assertSame( 'critical', $assessmentRow[ 'status' ] );
		$this->assertSame( 'critical', $assessmentRow[ 'drill_bucket' ] );
		$this->assertSame( 'bi bi-eye-slash-fill', $assessmentRow[ 'item_icon_class' ] );

		$rows = $provider->queueRows();
		$this->assertCount( 1, $rows[ 'active' ] );
		$this->assertSame( [], $rows[ 'ignored' ] );
		$row = $rows[ 'active' ][ 0 ];
		$this->assertSame( 'Cloaked Plugin', $row[ 'title' ] );
		$this->assertSame( $finding->cloakReasonSummary(), $row[ 'summary' ] );
		$this->assertFalse( $row[ 'is_ignored' ] );
		$this->assertSame( '', $row[ 'badge_label' ] );
		$this->assertSame( '/deactivate-cloaked', $row[ 'actions' ][ 0 ][ 'href' ] );
		$this->assertFalse( $row[ 'actions' ][ 0 ][ 'is_action' ] );
		$this->assertSame( 'ignore', $row[ 'actions' ][ 1 ][ 'kind' ] );
		$this->assertSame( 'hidden_plugins', $row[ 'actions' ][ 1 ][ 'maintenance_key' ] );
		$this->assertSame( CloakedPluginIgnore::SLUG, $this->decodeAction( $row[ 'actions' ][ 1 ] )[ 'ex' ] );
		$this->assertSame( $finding->identityKey(), $this->decodeAction( $row[ 'actions' ][ 1 ] )[ 'identifier' ] );
		$this->assertSame(
			[ 'value' => 'wp-content/plugins/cloaked/cloaked.php', 'style' => 'code' ],
			$this->detailNamed( $row, 'Path' )
		);
		$this->assertSame( 'Active', $this->detailNamed( $row, 'Status' )[ 'value' ] );
	}

	public function test_clear_state_is_good_without_attention_item() :void {
		$provider = new CloakedPluginsQueueIssueProviderTestDouble( [] );

		$this->assertSame( [], $provider->attentionItems() );
		$assessmentRow = $provider->assessmentRows()[ 0 ];
		$this->assertSame( 'good', $assessmentRow[ 'status' ] );
		$this->assertSame( 'critical', $assessmentRow[ 'drill_bucket' ] );
		$this->assertSame( [ 'active' => [], 'ignored' => [] ], $provider->queueRows() );
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

		$row = $provider->queueRows()[ 'active' ][ 0 ];

		$this->assertSame( '/plugins-search', $row[ 'actions' ][ 0 ][ 'href' ] );
		$this->assertSame( CloakedPluginIgnore::SLUG, $this->decodeAction( $row[ 'actions' ][ 1 ] )[ 'ex' ] );
		$this->assertSame( 'Inactive', $this->detailNamed( $row, 'Status' )[ 'value' ] );
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

		$row = $provider->queueRows()[ 'active' ][ 0 ];

		$this->assertCount( 1, $row[ 'actions' ] );
		$this->assertSame( CloakedPluginIgnore::SLUG, $this->decodeAction( $row[ 'actions' ][ 0 ] )[ 'ex' ] );
		$this->assertSame( 'wp-content/mu-plugins/loader.php', $this->detailNamed( $row, 'Path' )[ 'value' ] );
		$this->assertNotSame( '', $this->detailNamed( $row, 'Recommended action' )[ 'value' ] );
	}

	public function test_ignored_plugin_is_listed_apart_and_can_be_restored_without_active_attention_item() :void {
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

		$rows = $provider->queueRows();
		$this->assertSame( [], $rows[ 'active' ] );
		$this->assertCount( 1, $rows[ 'ignored' ] );

		$row = $rows[ 'ignored' ][ 0 ];
		$this->assertTrue( $row[ 'is_ignored' ] );
		$this->assertSame( 'Ignored', $row[ 'badge_label' ] );
		$this->assertCount( 1, $row[ 'actions' ] );
		$this->assertSame( 'unignore', $row[ 'actions' ][ 0 ][ 'kind' ] );
		$this->assertSame( CloakedPluginUnignore::SLUG, $this->decodeAction( $row[ 'actions' ][ 0 ] )[ 'ex' ] );
		$this->assertSame( $ignoredFinding->identityKey(), $this->decodeAction( $row[ 'actions' ][ 0 ] )[ 'identifier' ] );
		$this->assertNull( $this->detailNamed( $row, 'Recommended action' ) );
	}

	public function test_auto_ignored_shield_mu_has_no_queue_actions() :void {
		$ignoredFinding = $this->finding(
			new PluginEntry( PluginType::MustUse, MUHandler::PLUGIN_FILE_NAME, 'Shield MU', '1.0', '/mu-plugins/'.MUHandler::PLUGIN_FILE_NAME ),
			[ CloakReason::ShowAdvancedPlugins ],
			true
		);
		$provider = new CloakedPluginsQueueIssueProviderTestDouble(
			[],
			'',
			'',
			[ $ignoredFinding ],
			[ $ignoredFinding->identityKey() ]
		);

		$this->assertSame( [], $provider->queueRows()[ 'ignored' ][ 0 ][ 'actions' ] );
	}

	public function test_active_shield_mu_has_no_ignore_queue_action() :void {
		$activeFinding = $this->finding(
			new PluginEntry( PluginType::MustUse, MUHandler::PLUGIN_FILE_NAME, 'Shield MU', '1.0', '/mu-plugins/'.MUHandler::PLUGIN_FILE_NAME ),
			[ CloakReason::ShowAdvancedPlugins ],
			true
		);
		$provider = new CloakedPluginsQueueIssueProviderTestDouble(
			[ $activeFinding ],
			'',
			'',
			[],
			[ $activeFinding->identityKey() ]
		);

		$this->assertSame( [], $provider->queueRows()[ 'active' ][ 0 ][ 'actions' ] );
	}

	public function test_unavailable_detection_reports_nothing_to_the_queue() :void {
		$this->installCapability( false );
		$finding = $this->finding(
			new PluginEntry( PluginType::Standard, 'cloaked/cloaked.php', 'Cloaked Plugin', '1.2.3', '/plugins/cloaked/cloaked.php' ),
			[ CloakReason::AllPlugins ],
			true
		);
		$provider = new CloakedPluginsQueueIssueProviderTestDouble( [ $finding ], '', '', [ $finding ] );

		$this->assertSame( [], $provider->attentionItems() );
		$this->assertSame( [], $provider->assessmentRows() );
	}

	private function installCapability( bool $available ) :void {
		UnitTestControllerFactory::install( null, null, (object)[
			'caps' => new class( $available ) {
				private bool $available;

				public function __construct( bool $available ) {
					$this->available = $available;
				}

				public function canDetectCloakedPlugins() :bool {
					return $this->available;
				}
			},
		] );
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
		return \json_decode( $action[ 'ajax_action_json' ], true );
	}

	/**
	 * @return array{value:string,style:string}|null
	 */
	private function detailNamed( array $row, string $label ) :?array {
		foreach ( $row[ 'details' ][ 'items' ] as $item ) {
			if ( $item[ 'label' ] === $label ) {
				return [ 'value' => $item[ 'value' ], 'style' => $item[ 'style' ] ];
			}
		}
		return null;
	}

}

class CloakedPluginsQueueIssueProviderTestDouble extends CloakedPluginsQueueIssueProvider {

	private array $activeFindings;
	private array $ignoredFindings;
	private string $deactivateUrl;
	private string $pluginsSearchUrl;
	private array $shieldMuLoaderIdentities;

	public function __construct(
		array $activeFindings,
		string $deactivateUrl = '',
		string $pluginsSearchUrl = '',
		array $ignoredFindings = [],
		array $shieldMuLoaderIdentities = []
	) {
		$this->activeFindings = $activeFindings;
		$this->ignoredFindings = $ignoredFindings;
		$this->deactivateUrl = $deactivateUrl;
		$this->pluginsSearchUrl = $pluginsSearchUrl;
		$this->shieldMuLoaderIdentities = $shieldMuLoaderIdentities;
	}

	protected function state() :array {
		return [
			'all'        => \array_merge( $this->activeFindings, $this->ignoredFindings ),
			'active'     => $this->activeFindings,
			'ignored'    => $this->ignoredFindings,
			'new_active' => [],
		];
	}

	protected function deactivateUrl( string $pluginFile ) :string {
		return $this->deactivateUrl;
	}

	protected function pluginsSearchUrl( string $pluginFile ) :string {
		return $this->pluginsSearchUrl;
	}

	protected function isShieldMuLoaderFinding( CloakedPluginFinding $finding ) :bool {
		return \in_array( $finding->identityKey(), $this->shieldMuLoaderIdentities, true );
	}
}
