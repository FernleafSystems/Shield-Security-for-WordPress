<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;

/**
 * @phpstan-import-type QueueAssetCard from ScansResultsViewBuilder
 * @phpstan-import-type QueueAssetPane from ScansResultsViewBuilder
 * @phpstan-import-type VulnerabilitiesPayload from ScansVulnerabilitiesBuilder
 */
class ActionsQueueGroupScanSource {

	private ?array $activePluginsPane = null;
	private ?array $activeThemesPane = null;
	private ?array $ignoredPluginsPane = null;
	private ?array $ignoredThemesPane = null;
	private ?array $vulnerabilitiesPayload = null;
	private ?int $ignoredWordpressCount = null;

	public function __construct(
		private ScansResultsViewBuilder $scansResultsViewBuilder,
		private ScansVulnerabilitiesBuilder $scansVulnerabilitiesBuilder,
		private ActionsQueueScanResultsOptions $queueScanResultsOptions
	) {
	}

	/**
	 * @return list<QueueAssetCard>
	 */
	public function activeCardsForSource( string $assetSource ) :array {
		return match ( $assetSource ) {
			'plugins' => $this->activePluginsPane()[ 'cards' ] ?? [],
			'themes' => $this->activeThemesPane()[ 'cards' ] ?? [],
			default => [],
		};
	}

	public function ignoredCountForSource( string $ignoredSource ) :int {
		return match ( $ignoredSource ) {
			'wordpress' => $this->ignoredWordpressCount(),
			'plugins' => $this->countQueueAssetPaneResults( $this->ignoredPluginsPane() ),
			'themes' => $this->countQueueAssetPaneResults( $this->ignoredThemesPane() ),
			default => 0,
		};
	}

	public function vulnerabilitySection( string $sectionKey ) :array {
		$section = $this->vulnerabilitiesPayload()[ 'sections' ][ $sectionKey ] ?? null;
		return \is_array( $section ) ? $section : [];
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
	 * @return QueueAssetPane
	 */
	private function activePluginsPane() :array {
		if ( $this->activePluginsPane === null ) {
			$this->activePluginsPane = $this->scansResultsViewBuilder->buildActionsQueuePluginsPane(
				$this->queueScanResultsOptions->activeOnly()
			);
		}

		return $this->activePluginsPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function activeThemesPane() :array {
		if ( $this->activeThemesPane === null ) {
			$this->activeThemesPane = $this->scansResultsViewBuilder->buildActionsQueueThemesPane(
				$this->queueScanResultsOptions->activeOnly()
			);
		}

		return $this->activeThemesPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function ignoredPluginsPane() :array {
		if ( $this->ignoredPluginsPane === null ) {
			$this->ignoredPluginsPane = $this->scansResultsViewBuilder->buildActionsQueuePluginsPane(
				$this->queueScanResultsOptions->ignoredOnly()
			);
		}

		return $this->ignoredPluginsPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function ignoredThemesPane() :array {
		if ( $this->ignoredThemesPane === null ) {
			$this->ignoredThemesPane = $this->scansResultsViewBuilder->buildActionsQueueThemesPane(
				$this->queueScanResultsOptions->ignoredOnly()
			);
		}

		return $this->ignoredThemesPane;
	}

	private function ignoredWordpressCount() :int {
		if ( $this->ignoredWordpressCount === null ) {
			$loader = new LoadFileScanResultsTableData();
			$loader->custom_record_retriever_wheres = [
				\sprintf( "%s.`meta_key`='is_in_core'", RetrieveBase::ABBR_RESULTITEMMETA ),
				\sprintf( "%s.`meta_value`=1", RetrieveBase::ABBR_RESULTITEMMETA ),
			];
			$loader->results_display_options = $this->queueScanResultsOptions->ignoredOnly();
			$this->ignoredWordpressCount = $loader->countAll();
		}

		return $this->ignoredWordpressCount;
	}

	private function countQueueAssetPaneResults( array $pane ) :int {
		return (int)\array_sum( \array_map(
			static fn( array $card ) :int => (int)( $card[ 'count_badge' ] ?? 0 ),
			$pane[ 'cards' ] ?? []
		) );
	}
}
