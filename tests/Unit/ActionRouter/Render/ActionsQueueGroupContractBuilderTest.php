<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Plugins;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetMetadataResolver,
	ActionsQueueAssetFileStatusDetail,
	ActionsQueueContextActionsBuilder,
	ActionsQueueDrillDownPresentationBuilder,
	ActionsQueueGroupContractBuilder,
	ActionsQueueGroupDefinitions,
	ActionsQueueScanResultScopeStateBuilder,
	ScanResultsDisplayOptions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\AjaxRenderPolicyAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};

class ActionsQueueGroupContractBuilderTest extends BaseUnitTest {

	use AjaxRenderPolicyAssertions;

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
		Functions\when( 'number_format_i18n' )->alias( static fn( $number ) :string => (string)$number );
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
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}
			},
			'service_request' => new class extends Request {
				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpusers' => new class extends Users {
				public function getCurrentWpUserId() {
					return 0;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_empty_group_keeps_scoped_asset_groups_on_direct_table_contract() :void {
		$builder = $this->newBuilder( [
			'plugin:example-plugin/example-plugin.php' => [
				'subject_type' => 'plugin',
				'subject_id'   => 'example-plugin/example-plugin.php',
				'title'        => 'example_plugin',
				'icon_class'   => 'bi bi-plug-fill',
				'has_update'   => false,
			],
			'theme:example-theme'                     => [
				'subject_type' => 'theme',
				'subject_id'   => 'example-theme',
				'title'        => 'example_theme',
				'icon_class'   => 'bi bi-palette-fill',
				'has_update'   => false,
			],
		] );

		$pluginGroup = $builder->buildEmptyGroup( 'plugins:example-plugin/example-plugin.php', 'fix_now' );
		$themeGroup = $builder->buildEmptyGroup( 'themes:example-theme', 'fix_now' );

		$this->assertSame( 'example_plugin', $pluginGroup[ 'label' ] );
		$this->assertSame( 'direct_table', $pluginGroup[ 'detail_shell' ] );
		$this->assertSame( 'expandable', $pluginGroup[ 'card_type' ] );
		$this->assertSame( ActionsQueueAssetFileStatusDetail::class, $pluginGroup[ 'render_action_class' ] );
		$this->assertSame(
			[
				'display_context'         => 'actions_queue',
				'results_display_options' => [
					'include_ignored'  => false,
					'include_repaired' => false,
					'include_deleted'  => false,
					'ignored_only'     => false,
				],
				'subject_type'            => 'plugin',
				'subject_id'              => 'example-plugin/example-plugin.php',
			],
			$pluginGroup[ 'render_action_data' ]
		);
		$this->assertSame( 'example_plugin', $pluginGroup[ 'selection' ][ 'label' ] );
		$this->assertSame( 'example_plugin', $pluginGroup[ 'selection' ][ 'header' ][ 'title' ] );
		$this->assertSame( [], $pluginGroup[ 'selection' ][ 'header' ][ 'actions' ] );
		$this->assertAjaxRenderPayloadAllowedByPolicy(
			$pluginGroup[ 'selection' ][ 'detail_render_action' ],
			'plugin scoped asset detail render'
		);
		$this->assertFalse( $pluginGroup[ 'is_interactive' ] );

		$this->assertSame( 'example_theme', $themeGroup[ 'label' ] );
		$this->assertSame( 'direct_table', $themeGroup[ 'detail_shell' ] );
		$this->assertSame( 'actions_queue', $themeGroup[ 'render_action_data' ][ 'display_context' ] );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$themeGroup[ 'render_action_data' ][ 'results_display_options' ]
		);
		$this->assertSame( 'theme', $themeGroup[ 'render_action_data' ][ 'subject_type' ] );
		$this->assertSame( 'example-theme', $themeGroup[ 'render_action_data' ][ 'subject_id' ] );
		$this->assertSame( 'example_theme', $themeGroup[ 'selection' ][ 'header' ][ 'title' ] );
		$this->assertSame( [], $themeGroup[ 'selection' ][ 'header' ][ 'actions' ] );
		$this->assertAjaxRenderPayloadAllowedByPolicy(
			$themeGroup[ 'selection' ][ 'detail_render_action' ],
			'theme scoped asset detail render'
		);
	}

	public function test_build_empty_group_uses_generic_base_group_when_scoped_asset_cannot_be_resolved() :void {
		$builder = $this->newBuilder();

		$group = $builder->buildEmptyGroup( 'plugins:missing-plugin/missing-plugin.php', 'fix_now' );

		$this->assertSame( 'asset_cards', $group[ 'detail_shell' ] );
		$this->assertSame( Plugins::class, $group[ 'render_action_class' ] );
		$this->assertSame( $group[ 'label' ], $group[ 'selection' ][ 'label' ] );
		$this->assertSame( $group[ 'label' ], $group[ 'selection' ][ 'header' ][ 'title' ] );
		$this->assertAjaxRenderPayloadAllowedByPolicy(
			$group[ 'selection' ][ 'detail_render_action' ],
			'generic group detail render'
		);
	}

	public function test_active_direct_scan_group_preserves_active_count_and_direct_table_contract() :void {
		$builder = $this->newBuilder(
			[],
			$this->newScopeStateBuilder( 1, 2 )
		);

		$groups = $builder->buildResolvedGroups( 'Fix now', [
			$this->groupSeed( [
				'key'                         => 'plugins:example-plugin/example-plugin.php',
				'definition_key'              => 'plugins',
				'label'                       => 'Example Plugin',
				'item_count'                  => 1,
				'detail_shell'                => 'direct_table',
				'render_action_class_override' => ActionsQueueAssetFileStatusDetail::class,
				'render_action_data_override' => ( new ScanResultsDisplayOptions() )->buildSubjectActionData(
					'plugin',
					'example-plugin/example-plugin.php'
				),
			] ),
		] );

		$this->assertArrayHasKey( 'plugins:example-plugin/example-plugin.php', $groups[ 'groups_indexed' ] );
		$group = $groups[ 'groups_indexed' ][ 'plugins:example-plugin/example-plugin.php' ];

		$this->assertSame( 1, $group[ 'item_count' ] );
		$this->assertSame( 'warning', $group[ 'status' ] );
		$this->assertSame( 'direct_table', $group[ 'detail_shell' ] );
	}

	public function test_healthy_direct_scan_group_with_only_ignored_results_is_interactive() :void {
		$builder = $this->newBuilder(
			[],
			$this->newScopeStateBuilder( 0, 2 )
		);

		$groups = $builder->buildResolvedGroups( 'Fix now', [
			$this->groupSeed( [
				'key'                         => 'malware',
				'definition_key'              => 'malware',
				'label'                       => 'Malware',
				'item_count'                  => 0,
				'status'                      => 'good',
				'is_interactive_override'     => false,
				'render_action_data_override' => [],
			] ),
		] );

		$this->assertArrayHasKey( 'malware', $groups[ 'groups_indexed' ] );
		$group = $groups[ 'groups_indexed' ][ 'malware' ];
		$header = $group[ 'selection' ][ 'header' ];

		$this->assertTrue( $group[ 'is_interactive' ] );
		$this->assertSame( 0, $group[ 'item_count' ] );
		$this->assertSame( 'good', $group[ 'status' ] );
		$this->assertSame( [], $header[ 'actions' ] );
		$this->assertSame(
			( new ScanResultsDisplayOptions() )->buildDisplayContextActionData(),
			$group[ 'render_action_data' ]
		);
		$this->assertSame(
			'scanresults_malware',
			(string)( $group[ 'selection' ][ 'detail_render_action' ][ 'render_slug' ] ?? '' )
		);
		$this->assertSame(
			'actions_queue',
			(string)( $group[ 'selection' ][ 'detail_render_action' ][ 'display_context' ] ?? '' )
		);
		$this->assertAjaxRenderPayloadAllowedByPolicy(
			$group[ 'selection' ][ 'detail_render_action' ],
			'healthy ignored malware detail render'
		);
	}

	public function test_empty_scoped_asset_group_with_only_ignored_results_uses_good_zero_header_without_actions() :void {
		$builder = $this->newBuilder(
			[
				'plugin:example-plugin/example-plugin.php' => [
					'subject_type' => 'plugin',
					'subject_id'   => 'example-plugin/example-plugin.php',
					'title'        => 'Example Plugin',
					'icon_class'   => 'bi bi-plug-fill',
					'has_update'   => false,
				],
			],
			$this->newScopeStateBuilder( 0, 2 )
		);

		$group = $builder->buildEmptyGroup( 'plugins:example-plugin/example-plugin.php', 'Fix now' );
		$header = $group[ 'selection' ][ 'header' ];

		$this->assertSame( 'good', $group[ 'status' ] );
		$this->assertSame( 0, $group[ 'item_count' ] );
		$this->assertSame( [], $header[ 'actions' ] );
		$this->assertSame( 'actions_queue', (string)( $group[ 'render_action_data' ][ 'display_context' ] ?? '' ) );
	}

	/**
	 * @param array<string,array<string,mixed>> $metadataByAsset
	 */
	private function newBuilder(
		array $metadataByAsset = [],
		?ActionsQueueScanResultScopeStateBuilder $scopeStateBuilder = null
	) :ActionsQueueGroupContractBuilder {
		$resolver = new class( $metadataByAsset ) extends ActionsQueueAssetMetadataResolver {

			private array $metadataByAsset;

			public function __construct( array $metadataByAsset ) {
				$this->metadataByAsset = $metadataByAsset;
			}

			public function resolve( string $assetType, string $assetKey ) :?array {
				return $this->metadataByAsset[ $assetType.':'.$assetKey ] ?? null;
			}
		};

		return new ActionsQueueGroupContractBuilder(
			new ActionsQueueGroupDefinitions(),
			new ActionsQueueDrillDownPresentationBuilder(),
			$resolver,
			null,
			new class extends ActionsQueueContextActionsBuilder {
				public function buildForGroup(
					string $definitionKey,
					string $label,
					string $detailShell,
					int $itemCount,
					array $renderActionData
				) :array {
					return [];
				}
			},
			$scopeStateBuilder ?? $this->newScopeStateBuilder( 0, 0 )
		);
	}

	private function newScopeStateBuilder( int $activeCount, int $ignoredCount ) :ActionsQueueScanResultScopeStateBuilder {
		return new class( $activeCount, $ignoredCount ) extends ActionsQueueScanResultScopeStateBuilder {
			private int $activeCount;
			private int $ignoredCount;

			public function __construct( int $activeCount, int $ignoredCount ) {
				$this->activeCount = $activeCount;
				$this->ignoredCount = $ignoredCount;
			}

			public function buildCountsForActionScope( string $type, string $file ) :array {
				return [
					'scope'         => [
						'type' => $type,
						'file' => $file,
					],
					'active_count'  => $this->activeCount,
					'ignored_count' => $this->ignoredCount,
				];
			}
		};
	}

	private function groupSeed( array $overrides = [] ) :array {
		return \array_merge( [
			'key'             => 'wordpress',
			'definition_key'  => 'wordpress',
			'label'           => 'WordPress Files',
			'item_count'      => 1,
			'status'          => 'warning',
			'narrative'       => '',
			'detail_shell'    => 'direct_table',
			'links'           => [],
			'management_link' => [],
			'detail_table'    => [],
			'attention_items' => [],
			'maintenance_rows' => [],
			'summary_row'     => [],
		], $overrides );
	}
}
