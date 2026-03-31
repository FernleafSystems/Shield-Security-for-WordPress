<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;

/**
 * @phpstan-import-type QueueAssetSummaryRecord from ActionsQueueScanAssetCardsBuilder
 * @phpstan-import-type VulnerabilitySection from ScansVulnerabilitiesBuilder
 * @phpstan-import-type VulnerabilitiesPayload from ScansVulnerabilitiesBuilder
 */
class ActionsQueueGroupScanSource {

	private ActionsQueueScanAssetCardsBuilder $scanAssetCardsBuilder;
	private ScansVulnerabilitiesBuilder $scansVulnerabilitiesBuilder;
	private ActionsQueueScanResultsOptions $queueScanResultsOptions;
	private ?array $activePluginSummaries = null;
	private ?array $activeThemeSummaries = null;
	private ?array $ignoredPluginSummaries = null;
	private ?array $ignoredThemeSummaries = null;
	private ?array $vulnerabilitiesPayload = null;
	private ?int $ignoredWordpressCount = null;

	public function __construct(
		ActionsQueueScanAssetCardsBuilder $scanAssetCardsBuilder,
		ScansVulnerabilitiesBuilder $scansVulnerabilitiesBuilder,
		ActionsQueueScanResultsOptions $queueScanResultsOptions
	) {
		$this->scanAssetCardsBuilder = $scanAssetCardsBuilder;
		$this->scansVulnerabilitiesBuilder = $scansVulnerabilitiesBuilder;
		$this->queueScanResultsOptions = $queueScanResultsOptions;
	}

	/**
	 * @return list<QueueAssetSummaryRecord>
	 */
	public function activeAssetSummariesForSource( string $assetSource ) :array {
		if ( $assetSource === 'plugins' ) {
			return $this->activePluginSummaries();
		}
		if ( $assetSource === 'themes' ) {
			return $this->activeThemeSummaries();
		}

		return [];
	}

	public function ignoredCountForSource( string $ignoredSource ) :int {
		if ( $ignoredSource === 'wordpress' ) {
			return $this->ignoredWordpressCount();
		}
		if ( $ignoredSource === 'plugins' ) {
			return $this->countQueueAssetSummaryResults( $this->ignoredPluginSummaries() );
		}
		if ( $ignoredSource === 'themes' ) {
			return $this->countQueueAssetSummaryResults( $this->ignoredThemeSummaries() );
		}

		return 0;
	}

	/**
	 * @phpstan-param 'vulnerable'|'abandoned' $sectionKey
	 * @return VulnerabilitySection
	 */
	public function vulnerabilitySection( string $sectionKey ) :array {
		return $this->vulnerabilitiesPayload()[ 'sections' ][ $sectionKey ];
	}

	/**
	 * @return VulnerabilitiesPayload
	 */
	private function vulnerabilitiesPayload() :array {
		if ( $this->vulnerabilitiesPayload === null ) {
			$this->vulnerabilitiesPayload = $this->scansVulnerabilitiesBuilder->build();
		}

		return $this->vulnerabilitiesPayload;
	}

	/**
	 * @return list<QueueAssetSummaryRecord>
	 */
	private function activePluginSummaries() :array {
		if ( $this->activePluginSummaries === null ) {
			$this->activePluginSummaries = $this->scanAssetCardsBuilder->buildSummaryRecords(
				'plugin',
				$this->queueScanResultsOptions->activeOnly()
			);
		}

		return $this->activePluginSummaries;
	}

	/**
	 * @return list<QueueAssetSummaryRecord>
	 */
	private function activeThemeSummaries() :array {
		if ( $this->activeThemeSummaries === null ) {
			$this->activeThemeSummaries = $this->scanAssetCardsBuilder->buildSummaryRecords(
				'theme',
				$this->queueScanResultsOptions->activeOnly()
			);
		}

		return $this->activeThemeSummaries;
	}

	/**
	 * @return list<QueueAssetSummaryRecord>
	 */
	private function ignoredPluginSummaries() :array {
		if ( $this->ignoredPluginSummaries === null ) {
			$this->ignoredPluginSummaries = $this->scanAssetCardsBuilder->buildSummaryRecords(
				'plugin',
				$this->queueScanResultsOptions->ignoredOnly()
			);
		}

		return $this->ignoredPluginSummaries;
	}

	/**
	 * @return list<QueueAssetSummaryRecord>
	 */
	private function ignoredThemeSummaries() :array {
		if ( $this->ignoredThemeSummaries === null ) {
			$this->ignoredThemeSummaries = $this->scanAssetCardsBuilder->buildSummaryRecords(
				'theme',
				$this->queueScanResultsOptions->ignoredOnly()
			);
		}

		return $this->ignoredThemeSummaries;
	}

	private function ignoredWordpressCount() :int {
		if ( $this->ignoredWordpressCount === null ) {
			$loader = new LoadFileScanResultsTableData();
			$loader->custom_record_retriever_wheres = [
				"`rim`.`meta_key`='is_in_core'",
				"`rim`.`meta_value`=1",
			];
			$loader->results_display_options = $this->queueScanResultsOptions->ignoredOnly();
			$this->ignoredWordpressCount = $loader->countAll();
		}

		return $this->ignoredWordpressCount;
	}

	/**
	 * @param list<QueueAssetSummaryRecord> $summaries
	 */
	private function countQueueAssetSummaryResults( array $summaries ) :int {
		return (int)\array_sum( \array_map(
			static fn( array $summary ) :int => (int)( $summary[ 'count_badge' ] ?? 0 ),
			$summaries
		) );
	}
}
