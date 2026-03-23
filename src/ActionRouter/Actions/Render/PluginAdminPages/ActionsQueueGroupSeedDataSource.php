<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type BucketSource from ActionsQueueBucketsBuilder
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-import-type QueueAssetCard from ScansResultsViewBuilder
 * @phpstan-import-type QueueAssetPane from ScansResultsViewBuilder
 * @phpstan-import-type VulnerabilitiesPayload from ScansVulnerabilitiesBuilder
 */
class ActionsQueueGroupSeedDataSource {

	private ?array $activePluginsPane = null;
	private ?array $activeThemesPane = null;
	private ?array $ignoredPluginsPane = null;
	private ?array $ignoredThemesPane = null;
	private ?array $vulnerabilitiesPayload = null;
	private ?int $ignoredWordpressCount = null;

	private \Closure $buildPluginsPane;
	private \Closure $buildThemesPane;
	private \Closure $buildVulnerabilitiesPayload;
	private \Closure $normalizeMaintenanceQueueItems;
	private \Closure $normalizeBucketMaintenanceQueueItems;
	private \Closure $getIgnoredWordpressCount;

	public function __construct(
		private ActionsQueueScanResultsOptions $queueScanResultsOptions,
		\Closure $buildPluginsPane,
		\Closure $buildThemesPane,
		\Closure $buildVulnerabilitiesPayload,
		\Closure $normalizeMaintenanceQueueItems,
		\Closure $normalizeBucketMaintenanceQueueItems,
		\Closure $getIgnoredWordpressCount
	) {
		$this->buildPluginsPane = $buildPluginsPane;
		$this->buildThemesPane = $buildThemesPane;
		$this->buildVulnerabilitiesPayload = $buildVulnerabilitiesPayload;
		$this->normalizeMaintenanceQueueItems = $normalizeMaintenanceQueueItems;
		$this->normalizeBucketMaintenanceQueueItems = $normalizeBucketMaintenanceQueueItems;
		$this->getIgnoredWordpressCount = $getIgnoredWordpressCount;
	}

	/**
	 * @return list<QueueAssetCard>
	 */
	public function activePluginCards() :array {
		return $this->activePluginsPane()[ 'cards' ] ?? [];
	}

	/**
	 * @return list<QueueAssetCard>
	 */
	public function activeThemeCards() :array {
		return $this->activeThemesPane()[ 'cards' ] ?? [];
	}

	public function ignoredPluginsCount() :int {
		return $this->countQueueAssetPaneResults( $this->ignoredPluginsPane() );
	}

	public function ignoredThemesCount() :int {
		return $this->countQueueAssetPaneResults( $this->ignoredThemesPane() );
	}

	public function ignoredWordpressCount() :int {
		if ( $this->ignoredWordpressCount === null ) {
			$this->ignoredWordpressCount = ( $this->getIgnoredWordpressCount )();
		}

		return $this->ignoredWordpressCount;
	}

	/**
	 * @return VulnerabilitiesPayload
	 */
	public function vulnerabilitiesPayload() :array {
		if ( $this->vulnerabilitiesPayload === null ) {
			$this->vulnerabilitiesPayload = ( $this->buildVulnerabilitiesPayload )();
		}

		return $this->vulnerabilitiesPayload;
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 * @return list<MaintenanceQueueItem>
	 */
	public function activeMaintenanceItems( array $bucketSource ) :array {
		return ( $this->normalizeMaintenanceQueueItems )( $this->maintenanceAttentionItems( $bucketSource ) );
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 * @return list<MaintenanceQueueItem>
	 */
	public function healthyMaintenanceItems( array $bucketSource, string $bucketKey ) :array {
		return ( $this->normalizeBucketMaintenanceQueueItems )( $this->maintenanceAttentionItems( $bucketSource ), $bucketKey );
	}

	/**
	 * @return QueueAssetPane
	 */
	private function activePluginsPane() :array {
		if ( $this->activePluginsPane === null ) {
			$this->activePluginsPane = ( $this->buildPluginsPane )( $this->queueScanResultsOptions->activeOnly() );
		}

		return $this->activePluginsPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function activeThemesPane() :array {
		if ( $this->activeThemesPane === null ) {
			$this->activeThemesPane = ( $this->buildThemesPane )( $this->queueScanResultsOptions->activeOnly() );
		}

		return $this->activeThemesPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function ignoredPluginsPane() :array {
		if ( $this->ignoredPluginsPane === null ) {
			$this->ignoredPluginsPane = ( $this->buildPluginsPane )( $this->queueScanResultsOptions->ignoredOnly() );
		}

		return $this->ignoredPluginsPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function ignoredThemesPane() :array {
		if ( $this->ignoredThemesPane === null ) {
			$this->ignoredThemesPane = ( $this->buildThemesPane )( $this->queueScanResultsOptions->ignoredOnly() );
		}

		return $this->ignoredThemesPane;
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 * @return list<AttentionItem>
	 */
	private function maintenanceAttentionItems( array $bucketSource ) :array {
		return \array_values( \array_filter(
			$bucketSource[ 'attention_items' ],
			static fn( array $item ) :bool => ( $item[ 'zone' ] ?? '' ) === 'maintenance'
		) );
	}

	private function countQueueAssetPaneResults( array $pane ) :int {
		return (int)\array_sum( \array_map(
			static fn( array $card ) :int => (int)( $card[ 'count_badge' ] ?? 0 ),
			$pane[ 'cards' ] ?? []
		) );
	}
}
