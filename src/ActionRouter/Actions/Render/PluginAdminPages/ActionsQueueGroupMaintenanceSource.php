<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type BucketSource from ActionsQueueBucketsBuilder
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 */
class ActionsQueueGroupMaintenanceSource {

	private MaintenanceQueueItemDisplayNormalizer $maintenanceQueueItemDisplayNormalizer;

	public function __construct(
		MaintenanceQueueItemDisplayNormalizer $maintenanceQueueItemDisplayNormalizer
	) {
		$this->maintenanceQueueItemDisplayNormalizer = $maintenanceQueueItemDisplayNormalizer;
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 * @return list<MaintenanceQueueItem>
	 */
	public function itemsForBucket( array $bucketSource, string $bucketKey ) :array {
		return $this->maintenanceQueueItemDisplayNormalizer->normalizeForBucket(
			$this->maintenanceAttentionItems( $bucketSource ),
			$bucketKey
		);
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 * @return list<AttentionItem>
	 */
	private function maintenanceAttentionItems( array $bucketSource ) :array {
		return \array_values( \array_filter(
			$bucketSource[ 'attention_items' ],
			static fn( array $item ) :bool => $item[ 'zone' ] === 'maintenance'
		) );
	}
}
