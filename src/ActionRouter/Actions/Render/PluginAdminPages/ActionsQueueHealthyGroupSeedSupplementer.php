<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type BucketSource from ActionsQueueBucketsBuilder
 * @phpstan-import-type GroupSeed from ActionsQueueGroupContractBuilder
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-import-type QueueAssetPane from ScansResultsViewBuilder
 */
class ActionsQueueHealthyGroupSeedSupplementer {

	private ?array $ignoredPluginsPane = null;
	private ?array $ignoredThemesPane = null;
	private ?int $ignoredWordpressCount = null;

	private \Closure $buildPluginsPane;
	private \Closure $buildThemesPane;
	private \Closure $normalizeBucketMaintenanceQueueItems;
	private \Closure $getIgnoredWordpressCount;

	public function __construct(
		private ActionsQueueGroupDefinitions $groupDefinitions,
		private ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder,
		private ActionsQueueScanResultsOptions $queueScanResultsOptions,
		\Closure $buildPluginsPane,
		\Closure $buildThemesPane,
		\Closure $normalizeBucketMaintenanceQueueItems,
		\Closure $getIgnoredWordpressCount
	) {
		$this->buildPluginsPane = $buildPluginsPane;
		$this->buildThemesPane = $buildThemesPane;
		$this->normalizeBucketMaintenanceQueueItems = $normalizeBucketMaintenanceQueueItems;
		$this->getIgnoredWordpressCount = $getIgnoredWordpressCount;
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 * @phpstan-param AssessmentRowsByZone $assessmentRowsByZone
	 * @param array<string,true> $existingGroupKeys
	 * @return list<GroupSeed>
	 */
	public function supplement(
		string $bucketKey,
		array $bucketSource,
		array $assessmentRowsByZone,
		array $existingGroupKeys
	) :array {
		$seeds = [];

		foreach ( $this->buildHealthyScanSeedsForBucket( $bucketKey, $assessmentRowsByZone[ 'scans' ] ?? [] ) as $seed ) {
			if ( isset( $existingGroupKeys[ $seed[ 'key' ] ] ) ) {
				continue;
			}
			$seeds[] = $seed;
			$existingGroupKeys[ $seed[ 'key' ] ] = true;
		}

		foreach ( $this->normalizeBucketMaintenanceQueueItems( \array_values( \array_filter(
			$bucketSource[ 'attention_items' ],
			static fn( array $item ) :bool => ( $item[ 'zone' ] ?? '' ) === 'maintenance'
		) ), $bucketKey ) as $maintenanceItem ) {
			if ( ( $maintenanceItem[ 'severity' ] ?? '' ) !== 'good'
				|| ( $maintenanceItem[ 'drill_bucket' ] ?? '' ) !== $bucketKey
				|| isset( $existingGroupKeys[ $maintenanceItem[ 'key' ] ] ) ) {
				continue;
			}

			$seeds[] = $this->maintenanceSeedBuilder->build( $maintenanceItem, true );
			$existingGroupKeys[ $maintenanceItem[ 'key' ] ] = true;
		}

		return $seeds;
	}

	/**
	 * @param list<AssessmentRow> $assessmentRows
	 * @return list<GroupSeed>
	 */
	private function buildHealthyScanSeedsForBucket( string $bucketKey, array $assessmentRows ) :array {
		$rowsByDefinitionKey = [];

		foreach ( $assessmentRows as $row ) {
			if ( $row[ 'status' ] !== 'good' || $row[ 'drill_bucket' ] !== $bucketKey ) {
				continue;
			}

			$definitionKey = $this->groupDefinitions->groupKeyForSummaryKey( $row[ 'key' ] );
			if ( $definitionKey === 'maintenance' ) {
				continue;
			}

			$rowsByDefinitionKey[ $definitionKey ][] = $row;
		}

		$seeds = [];
		foreach ( $rowsByDefinitionKey as $definitionKey => $rows ) {
			$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );
			$interaction = $this->buildHealthyScanInteraction( $definitionKey );
			$seeds[] = [
				'key'                         => $definitionKey,
				'is_healthy'                  => true,
				'definition_key'              => $definitionKey,
				'heading_label'               => $definition[ 'label' ],
				'label'                       => $definition[ 'label' ],
				'item_count'                  => $interaction[ 'item_count' ] > 0
					? $interaction[ 'item_count' ]
					: \count( $rows ),
				'status'                      => 'good',
				'narrative'                   => $this->combineHealthyAssessmentNarratives( $rows ),
				'detail_shell'                => $definition[ 'detail_shell' ],
				'links'                       => [],
				'management_link'             => [],
				'is_interactive_override'     => $interaction[ 'is_interactive' ],
				'detail_table'                => [],
				'render_action_data_override' => $interaction[ 'render_action_data' ],
				'attention_items'             => [],
				'maintenance_rows'            => [],
				'summary_row'                 => [],
			];
		}

		return $seeds;
	}

	/**
	 * @param list<AssessmentRow> $rows
	 */
	private function combineHealthyAssessmentNarratives( array $rows ) :string {
		return \implode( ' ', \array_values( \array_unique( \array_filter(
			\array_map(
				static fn( array $row ) :string => \trim( $row[ 'description' ] ),
				$rows
			)
		) ) ) );
	}

	/**
	 * @return array{
	 *   is_interactive:bool,
	 *   item_count:int,
	 *   render_action_data:array<string,mixed>
	 * }
	 */
	private function buildHealthyScanInteraction( string $definitionKey ) :array {
		switch ( $definitionKey ) {
			case 'wordpress':
				$ignoredCount = $this->ignoredWordpressCount();
				break;
			case 'plugins':
				$ignoredCount = $this->countQueueAssetPaneResults( $this->ignoredPluginsPane() );
				break;
			case 'themes':
				$ignoredCount = $this->countQueueAssetPaneResults( $this->ignoredThemesPane() );
				break;
			default:
				$ignoredCount = 0;
				break;
		}

		return [
			'is_interactive'    => $ignoredCount > 0,
			'item_count'        => $ignoredCount,
			'render_action_data' => $ignoredCount > 0
				? $this->queueScanResultsOptions->buildActionData( $this->queueScanResultsOptions->ignoredOnly() )
				: [],
		];
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

	private function ignoredWordpressCount() :int {
		if ( $this->ignoredWordpressCount === null ) {
			$this->ignoredWordpressCount = ( $this->getIgnoredWordpressCount )();
		}

		return $this->ignoredWordpressCount;
	}

	private function countQueueAssetPaneResults( array $pane ) :int {
		return (int)\array_sum( \array_map(
			static fn( array $card ) :int => (int)( $card[ 'count_badge' ] ?? 0 ),
			$pane[ 'cards' ] ?? []
		) );
	}

	/**
	 * @param list<array<string,mixed>> $items
	 * @return list<MaintenanceQueueItem>
	 */
	private function normalizeBucketMaintenanceQueueItems( array $items, string $bucketKey ) :array {
		return ( $this->normalizeBucketMaintenanceQueueItems )( $items, $bucketKey );
	}
}
