<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type QueueAssetSummaryRecord from ActionsQueueScanAssetCardsBuilder
 * @phpstan-import-type VulnerabilitySection from ScansVulnerabilitiesBuilder
 * @phpstan-import-type VulnerabilitiesPayload from ScansVulnerabilitiesBuilder
 */
class ActionsQueueGroupScanSource {

	private ActionsQueueScanAssetCardsBuilder $scanAssetCardsBuilder;
	private ScansVulnerabilitiesBuilder $scansVulnerabilitiesBuilder;
	private ScanResultsDisplayOptions $queueScanResultsOptions;
	private ?array $activePluginSummaries = null;
	private ?array $activeThemeSummaries = null;
	private array $fullyIgnoredAssetSummaries = [];
	private ?array $vulnerabilitiesPayload = null;
	private ?int $ignoredWordpressCount = null;
	private ?int $ignoredMalwareCount = null;

	public function __construct(
		ActionsQueueScanAssetCardsBuilder $scanAssetCardsBuilder,
		ScansVulnerabilitiesBuilder $scansVulnerabilitiesBuilder,
		ScanResultsDisplayOptions $queueScanResultsOptions
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
		if ( $ignoredSource === 'malware' ) {
			return $this->ignoredMalwareCount();
		}
		if ( $ignoredSource === 'plugins' ) {
			return $this->countQueueAssetSummaryResults( $this->fullyIgnoredAssetSummariesForSource( 'plugins' ) );
		}
		if ( $ignoredSource === 'themes' ) {
			return $this->countQueueAssetSummaryResults( $this->fullyIgnoredAssetSummariesForSource( 'themes' ) );
		}

		return 0;
	}

	/**
	 * @return list<QueueAssetSummaryRecord>
	 */
	public function fullyIgnoredAssetSummariesForSource( string $assetSource ) :array {
		$assetType = $assetSource === 'plugins'
			? 'plugin'
			: ( $assetSource === 'themes' ? 'theme' : '' );
		if ( $assetType === '' ) {
			return [];
		}

		if ( !isset( $this->fullyIgnoredAssetSummaries[ $assetSource ] ) ) {
			$this->fullyIgnoredAssetSummaries[ $assetSource ] = $this->scanAssetCardsBuilder
				->buildFullyIgnoredSummaryRecords( $assetType );
		}

		return $this->fullyIgnoredAssetSummaries[ $assetSource ];
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

	private function ignoredWordpressCount() :int {
		if ( $this->ignoredWordpressCount === null ) {
			$this->ignoredWordpressCount = $this->buildScanResultsTableBuilder()->countForScope(
				'wordpress',
				'wordpress',
				$this->queueScanResultsOptions->ignoredOnly()
			);
		}

		return $this->ignoredWordpressCount;
	}

	private function ignoredMalwareCount() :int {
		if ( $this->ignoredMalwareCount === null ) {
			$this->ignoredMalwareCount = $this->buildScanResultsTableBuilder()->countForScope(
				'malware',
				'malware',
				$this->queueScanResultsOptions->ignoredOnly()
			);
		}

		return $this->ignoredMalwareCount;
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

	private function buildScanResultsTableBuilder() :ActionsQueueScanResultsTableBuilder {
		return new ActionsQueueScanResultsTableBuilder( null, $this->queueScanResultsOptions );
	}
}
