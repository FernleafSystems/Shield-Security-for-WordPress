<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueGroupScanSource,
	ActionsQueueScanAssetCardsBuilder,
	ScanResultsDisplayOptions,
	ScansVulnerabilitiesBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueGroupScanSourceTest extends BaseUnitTest {

	public function test_fully_ignored_assets_reuse_memoized_active_summaries() :void {
		$assetBuilder = new ActionsQueueGroupScanSourceAssetBuilderSpy(
			[
				'plugin' => [
					$this->assetSummary( 'active-plugin/active-plugin.php', 2 ),
				],
			],
			[
				'plugin' => [
					$this->assetSummary( 'ignored-plugin/ignored-plugin.php', 3 ),
				],
			]
		);
		$source = new ActionsQueueGroupScanSource(
			$assetBuilder,
			new ScansVulnerabilitiesBuilder(),
			new ScanResultsDisplayOptions()
		);

		$ignoredCount = $source->ignoredCountForSource( 'plugins' );
		$activeSummaries = $source->activeAssetSummariesForSource( 'plugins' );

		$this->assertSame( 3, $ignoredCount );
		$this->assertSame( [ 'plugin' ], $assetBuilder->summaryCalls );
		$this->assertSame( [ 'plugin' ], $assetBuilder->fullyIgnoredCalls );
		$this->assertSame(
			[ 'active-plugin/active-plugin.php' ],
			\array_column( $assetBuilder->fullyIgnoredActiveSummaryArgs[ 'plugin' ], 'key' )
		);
		$this->assertSame( [ 'active-plugin/active-plugin.php' ], \array_column( $activeSummaries, 'key' ) );
	}

	private function assetSummary( string $key, int $count ) :array {
		return [
			'key'          => $key,
			'status'       => 'warning',
			'icon_class'   => 'bi bi-plug-fill',
			'title'        => $key,
			'stat_text'    => 'needs review',
			'meta_text'    => $key,
			'count_badge'  => $count,
			'subject_type' => 'plugin',
			'subject_id'   => $key,
			'has_update'   => false,
		];
	}
}

class ActionsQueueGroupScanSourceAssetBuilderSpy extends ActionsQueueScanAssetCardsBuilder {

	public array $summaryCalls = [];

	public array $fullyIgnoredCalls = [];

	public array $fullyIgnoredActiveSummaryArgs = [];

	private array $activeSummaries;

	private array $fullyIgnoredSummaries;

	public function __construct( array $activeSummaries, array $fullyIgnoredSummaries ) {
		$this->activeSummaries = $activeSummaries;
		$this->fullyIgnoredSummaries = $fullyIgnoredSummaries;
	}

	public function buildSummaryRecords( string $assetType, array $resultsDisplayOptions = [] ) :array {
		$this->summaryCalls[] = $assetType;
		return $this->activeSummaries[ $assetType ] ?? [];
	}

	public function buildFullyIgnoredSummaryRecords( string $assetType, array $activeSummaries ) :array {
		$this->fullyIgnoredCalls[] = $assetType;
		$this->fullyIgnoredActiveSummaryArgs[ $assetType ] = $activeSummaries;
		return $this->fullyIgnoredSummaries[ $assetType ] ?? [];
	}
}
