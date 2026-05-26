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

	public function test_active_assets_are_memoized_per_source() :void {
		$assetBuilder = new ActionsQueueGroupScanSourceAssetBuilderSpy(
			[
				'plugin' => [
					$this->assetSummary( 'active-plugin/active-plugin.php', 2 ),
				],
			]
		);
		$source = new ActionsQueueGroupScanSource(
			$assetBuilder,
			new ScansVulnerabilitiesBuilder(),
			new ScanResultsDisplayOptions()
		);

		$activeSummaries = $source->activeAssetSummariesForSource( 'plugins' );
		$memoizedSummaries = $source->activeAssetSummariesForSource( 'plugins' );

		$this->assertSame( [ 'plugin' ], $assetBuilder->summaryCalls );
		$this->assertSame( [ 'active-plugin/active-plugin.php' ], \array_column( $activeSummaries, 'key' ) );
		$this->assertSame( $activeSummaries, $memoizedSummaries );
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

	private array $activeSummaries;

	public function __construct( array $activeSummaries ) {
		$this->activeSummaries = $activeSummaries;
	}

	public function buildSummaryRecords( string $assetType, array $resultsDisplayOptions = [] ) :array {
		$this->summaryCalls[] = $assetType;
		return $this->activeSummaries[ $assetType ] ?? [];
	}
}
