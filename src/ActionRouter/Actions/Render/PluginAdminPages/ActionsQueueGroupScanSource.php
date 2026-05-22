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
	private ?array $vulnerabilitiesPayload = null;

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

}
