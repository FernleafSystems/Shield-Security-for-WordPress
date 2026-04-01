<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetMetadataResolver,
	ActionsQueueScanAssetCardsBuilder
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
		$this->assertSame( [ 'Busy Plugin', 'Example Plugin' ], \array_column( $records, 'title' ) );
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
		$this->assertSame( 'example-plugin/example-plugin.php', $records[ 0 ][ 'table' ][ 'subject_id' ] );
		$this->assertSame( [ 'update', 'deactivate' ], \array_column( $records[ 0 ][ 'actions' ], 'type' ) );
		$this->assertSame( 'bi bi-arrow-up-circle-fill', $records[ 0 ][ 'actions' ][ 0 ][ 'icon_class' ] );
		$this->assertSame( 'Go to updates', $records[ 0 ][ 'actions' ][ 0 ][ 'tooltip_attr' ] );
		$this->assertSame( '1', $records[ 0 ][ 'panel_data' ][ 'actions-queue-asset-panel-loaded' ] ?? '' );
		$this->assertSame( '0', $records[ 0 ][ 'panel_data' ][ 'actions-queue-asset-panel-lazy' ] ?? '' );
		$this->assertSame( 1, $builder->tableBuildCalls() );
	}

	/**
	 * @param list<array{slug:string,file_count:int}> $groupedRows
	 * @param array<string,array<string,mixed>> $metadataBySlug
	 */
	private function newBuilder( array $groupedRows, array $metadataBySlug ) :object {
		$resolver = new class( $metadataBySlug ) extends ActionsQueueAssetMetadataResolver {

			private array $metadataBySlug;

			public function __construct( array $metadataBySlug ) {
				$this->metadataBySlug = $metadataBySlug;
			}

			public function resolve( string $assetType, string $assetKey ) :?array {
				return $this->metadataBySlug[ $assetKey ] ?? null;
			}
		};

		return new class( $resolver, $groupedRows ) extends ActionsQueueScanAssetCardsBuilder {
			private int $tableBuildCalls = 0;
			private array $groupedRows;

			public function __construct( ActionsQueueAssetMetadataResolver $resolver, array $groupedRows ) {
				parent::__construct( $resolver );
				$this->groupedRows = $groupedRows;
			}

			protected function retrieveGroupedAssetSummaries( string $assetType, array $resultsDisplayOptions ) :array {
				return $this->groupedRows;
			}

			protected function buildFileStatusTable( string $subjectType, string $subjectId, array $resultsDisplayOptions ) :array {
				$this->tableBuildCalls++;
				return [
					'table_type'   => 'file_scan_results',
					'subject_type' => $subjectType,
					'subject_id'   => $subjectId,
				];
			}

			public function tableBuildCalls() :int {
				return $this->tableBuildCalls;
			}
		};
	}
}
