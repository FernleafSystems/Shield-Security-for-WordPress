<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetMetadataResolver,
	ActionsQueueScanAssetCardsBuilder,
	ActionsQueueScanResultsTableBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueScanAssetCardsBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		Functions\when( 'admin_url' )->alias( static fn( string $path = '' ) :string => '/wp-admin/'.\ltrim( $path, '/' ) );
	}

	public function test_build_summary_records_uses_grouped_asset_rows_without_building_tables() :void {
		$builder = $this->newBuilder(
			[
				[ 'slug' => 'example-plugin/example-plugin.php', 'file_count' => 1 ],
				[ 'slug' => 'busy-plugin/busy-plugin.php', 'file_count' => 3 ],
				[ 'slug' => 'missing-plugin/missing-plugin.php', 'file_count' => 5 ],
			],
			[
				'example-plugin/example-plugin.php' => [
					'subject_type' => 'plugin',
					'subject_id'   => 'example-plugin/example-plugin.php',
					'title'        => 'Example Plugin',
					'icon_class'   => 'bi bi-plug-fill',
					'has_update'   => false,
				],
				'busy-plugin/busy-plugin.php'    => [
					'subject_type' => 'plugin',
					'subject_id'   => 'busy-plugin/busy-plugin.php',
					'title'        => 'Busy Plugin',
					'icon_class'   => 'bi bi-plug-fill',
					'has_update'   => false,
				],
			]
		);

		$records = $builder->buildSummaryRecords( 'plugin' );

		$this->assertSame( [ 'busy-plugin/busy-plugin.php', 'example-plugin/example-plugin.php' ], \array_column( $records, 'key' ) );
		$this->assertSame( [ 3, 1 ], \array_column( $records, 'count_badge' ) );
		$this->assertSame( 0, $builder->tableBuildCalls() );
	}

	public function test_build_issue_records_adds_actions_tables_and_panel_contract() :void {
		$builder = $this->newBuilder(
			[
				[ 'slug' => 'example-plugin/example-plugin.php', 'file_count' => 2 ],
			],
			[
				'example-plugin/example-plugin.php' => [
					'subject_type' => 'plugin',
					'subject_id'   => 'example-plugin/example-plugin.php',
					'title'        => 'Example Plugin',
					'icon_class'   => 'bi bi-plug-fill',
					'has_update'   => true,
				],
			]
		);

		$records = $builder->buildIssueRecords( 'plugin' );

		$this->assertCount( 1, $records );
		$this->assertSame( 'example-plugin/example-plugin.php', $records[ 0 ][ 'key' ] );
		$this->assertSame( 2, $records[ 0 ][ 'count_badge' ] );
		$this->assertSame( 'plugin', $records[ 0 ][ 'table' ][ 'route' ] );
		$this->assertSame( 'example-plugin/example-plugin.php', $records[ 0 ][ 'table' ][ 'subject_id' ] );
		$this->assertSame( [ 'update', 'deactivate' ], \array_column( $records[ 0 ][ 'actions' ], 'type' ) );
		$this->assertSame( '', $records[ 0 ][ 'body_notice' ] );
		$this->assertSame( '', $records[ 0 ][ 'body_notice_variant' ] );
		$this->assertSame( '1', $records[ 0 ][ 'panel_data' ][ 'actions-queue-asset-panel-loaded' ] ?? '' );
		$this->assertSame( '0', $records[ 0 ][ 'panel_data' ][ 'actions-queue-asset-panel-lazy' ] ?? '' );
		$this->assertSame( 1, $builder->tableBuildCalls() );
		$this->assertSame( [ 'plugin' ], $builder->tableBuildRoutes() );
	}

	public function test_build_issue_records_uses_theme_table_route_for_theme_subjects() :void {
		$builder = $this->newBuilder(
			[
				[ 'slug' => 'example-theme', 'file_count' => 4 ],
			],
			[
				'example-theme' => [
					'subject_type' => 'theme',
					'subject_id'   => 'example-theme',
					'title'        => 'Example Theme',
					'icon_class'   => 'bi bi-palette-fill',
					'has_update'   => false,
				],
			]
		);

		$records = $builder->buildIssueRecords( 'theme' );

		$this->assertCount( 1, $records );
		$this->assertSame( 'example-theme', $records[ 0 ][ 'table' ][ 'subject_id' ] );
		$this->assertSame( [ 'theme' ], $builder->tableBuildRoutes() );
	}

	public function test_build_fully_ignored_summary_records_filters_out_assets_with_active_results() :void {
		$builder = $this->newBuilder(
			[
				[ 'slug' => 'active-plugin/active-plugin.php', 'file_count' => 2 ],
			],
			[
				'active-plugin/active-plugin.php' => [
					'subject_type' => 'plugin',
					'subject_id'   => 'active-plugin/active-plugin.php',
					'title'        => 'Active Plugin',
					'icon_class'   => 'bi bi-plug-fill',
					'has_update'   => false,
				],
				'ignored-plugin/ignored-plugin.php' => [
					'subject_type' => 'plugin',
					'subject_id'   => 'ignored-plugin/ignored-plugin.php',
					'title'        => 'Ignored Plugin',
					'icon_class'   => 'bi bi-plug-fill',
					'has_update'   => false,
				],
			],
			[
				[ 'slug' => 'active-plugin/active-plugin.php', 'file_count' => 1 ],
				[ 'slug' => 'ignored-plugin/ignored-plugin.php', 'file_count' => 3 ],
			]
		);

		$records = $builder->buildFullyIgnoredSummaryRecords( 'plugin' );

		$this->assertSame( [ 'ignored-plugin/ignored-plugin.php' ], \array_column( $records, 'key' ) );
		$this->assertSame( [ 3 ], \array_column( $records, 'count_badge' ) );
		$this->assertNotSame( '', (string)( $records[ 0 ][ 'stat_text' ] ?? '' ) );
	}

	public function test_build_fully_ignored_summary_records_supports_theme_assets() :void {
		$builder = $this->newBuilder(
			[
				[ 'slug' => 'active-theme', 'file_count' => 2 ],
			],
			[
				'active-theme' => [
					'subject_type' => 'theme',
					'subject_id'   => 'active-theme',
					'title'        => 'asset-title-active',
					'icon_class'   => 'bi bi-palette-fill',
					'has_update'   => false,
				],
				'ignored-theme' => [
					'subject_type' => 'theme',
					'subject_id'   => 'ignored-theme',
					'title'        => 'asset-title-ignored',
					'icon_class'   => 'bi bi-palette-fill',
					'has_update'   => false,
				],
			],
			[
				[ 'slug' => 'active-theme', 'file_count' => 1 ],
				[ 'slug' => 'ignored-theme', 'file_count' => 4 ],
			]
		);

		$records = $builder->buildFullyIgnoredSummaryRecords( 'theme' );

		$this->assertSame( [ 'ignored-theme' ], \array_column( $records, 'key' ) );
		$this->assertSame( [ 4 ], \array_column( $records, 'count_badge' ) );
		$this->assertNotSame( '', (string)( $records[ 0 ][ 'stat_text' ] ?? '' ) );
	}

	/**
	 * @param list<array{slug:string,file_count:int}> $activeGroupedRows
	 * @param array<string,array<string,mixed>> $metadataBySlug
	 * @param list<array{slug:string,file_count:int}>|null $ignoredGroupedRows
	 */
	private function newBuilder( array $activeGroupedRows, array $metadataBySlug, ?array $ignoredGroupedRows = null ) :object {
		$resolver = new class( $metadataBySlug ) extends ActionsQueueAssetMetadataResolver {

			private array $metadataBySlug;

			public function __construct( array $metadataBySlug ) {
				$this->metadataBySlug = $metadataBySlug;
			}

			public function resolve( string $assetType, string $assetKey ) :?array {
				return $this->metadataBySlug[ $assetKey ] ?? null;
			}
		};

		return new class( $resolver, $activeGroupedRows, $ignoredGroupedRows ?? $activeGroupedRows ) extends ActionsQueueScanAssetCardsBuilder {
			private array $activeGroupedRows;
			private array $ignoredGroupedRows;
			private \stdClass $tableBuildRecorder;

			public function __construct(
				ActionsQueueAssetMetadataResolver $resolver,
				array $activeGroupedRows,
				array $ignoredGroupedRows
			) {
				parent::__construct( $resolver );
				$this->activeGroupedRows = $activeGroupedRows;
				$this->ignoredGroupedRows = $ignoredGroupedRows;
				$this->tableBuildRecorder = (object)[ 'calls' => [] ];
			}

			protected function retrieveGroupedAssetSummaries( string $assetType, array $resultsDisplayOptions ) :array {
				return !empty( $resultsDisplayOptions[ 'ignored_only' ] )
					? $this->ignoredGroupedRows
					: $this->activeGroupedRows;
			}

			protected function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
				return new class( $this->tableBuildRecorder ) extends ActionsQueueScanResultsTableBuilder {
					private \stdClass $recorder;

					public function __construct( \stdClass $recorder ) {
						$this->recorder = $recorder;
					}

					public function buildPluginTable( string $pluginFile, ?array $options = null ) :array {
						$this->recorder->calls[] = [
							'route'      => 'plugin',
							'subject_id' => $pluginFile,
							'options'    => $options,
						];
						return [
							'table_type' => 'file_scan_results',
							'route'      => 'plugin',
							'subject_id' => $pluginFile,
						];
					}

					public function buildThemeTable( string $stylesheet, ?array $options = null ) :array {
						$this->recorder->calls[] = [
							'route'      => 'theme',
							'subject_id' => $stylesheet,
							'options'    => $options,
						];
						return [
							'table_type' => 'file_scan_results',
							'route'      => 'theme',
							'subject_id' => $stylesheet,
						];
					}
				};
			}

			public function tableBuildCalls() :int {
				return \count( $this->tableBuildRecorder->calls );
			}

			public function tableBuildRoutes() :array {
				return \array_column( $this->tableBuildRecorder->calls, 'route' );
			}
		};
	}
}
