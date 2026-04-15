<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type BucketSource from ActionsQueueBucketsBuilder
 * @phpstan-import-type GroupLink from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupSeed from ActionsQueueGroupContractBuilder
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-import-type QueueAssetSummaryRecord from ActionsQueueScanAssetCardsBuilder
 * @phpstan-import-type VulnerabilityAction from ScansVulnerabilitiesBuilder
 */
class ActionsQueueGroupSeedCollector {

	private ActionsQueueGroupDefinitions $groupDefinitions;
	private ActionsQueueScanResultsOptions $queueScanResultsOptions;
	private ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder;
	private ActionsQueueGroupScanSource $scanSource;
	private ActionsQueueGroupMaintenanceSource $maintenanceSource;

	public function __construct(
		ActionsQueueGroupDefinitions $groupDefinitions,
		ActionsQueueScanResultsOptions $queueScanResultsOptions,
		ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder,
		ActionsQueueGroupScanSource $scanSource,
		ActionsQueueGroupMaintenanceSource $maintenanceSource
	) {
		$this->groupDefinitions = $groupDefinitions;
		$this->queueScanResultsOptions = $queueScanResultsOptions;
		$this->maintenanceSeedBuilder = $maintenanceSeedBuilder;
		$this->scanSource = $scanSource;
		$this->maintenanceSource = $maintenanceSource;
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 * @return list<GroupSeed>
	 */
	public function collect( string $bucketKey, array $bucketSource ) :array {
		$seeds = [];
		$maintenanceItemsByGroupKey = null;
		$maintenanceGroupsBuilt = [];
		$pluginsExpanded = false;
		$themesExpanded = false;
		$vulnerableExpanded = false;
		$abandonedExpanded = false;
		$pluginAttentionItems = \array_values( \array_filter(
			$bucketSource[ 'attention_items' ],
			static fn( array $item ) :bool => \in_array( $item[ 'key' ], [ 'plugin_files', 'plugin_files_ignored' ], true )
		) );

		foreach ( $bucketSource[ 'attention_items' ] as $item ) {
			$summaryBehaviour = $this->groupDefinitions->summaryBehaviourForKey( $item[ 'key' ] );
			$definitionKey = $summaryBehaviour[ 'definition_key' ];

			switch ( $summaryBehaviour[ 'seed_strategy' ] ) {
				case 'plugin_assets':
					if ( !$pluginsExpanded ) {
						$pluginsExpanded = true;
						$seeds = \array_merge(
							$seeds,
							$this->buildPluginAssetSeeds( $pluginAttentionItems )
						);
					}
					continue 2;

				case 'asset_cards':
					if ( $summaryBehaviour[ 'asset_source' ] === 'themes' && !$themesExpanded ) {
						$themesExpanded = true;
						$seeds = \array_merge(
							$seeds,
							$this->buildAssetSeeds( 'themes', $summaryBehaviour[ 'asset_source' ], $item )
						);
					}
					continue 2;

				case 'vulnerability_section':
					if ( $summaryBehaviour[ 'vulnerability_section' ] === 'vulnerable' && !$vulnerableExpanded ) {
						$vulnerableExpanded = true;
						$seeds = \array_merge(
							$seeds,
							$this->buildVulnerabilitySeeds(
								$summaryBehaviour[ 'definition_key' ],
								$this->scanSource->vulnerabilitySection( $summaryBehaviour[ 'vulnerability_section' ] ),
								$item
							)
						);
					}
					elseif ( $summaryBehaviour[ 'vulnerability_section' ] === 'abandoned' && !$abandonedExpanded ) {
						$abandonedExpanded = true;
						$seeds = \array_merge(
							$seeds,
							$this->buildVulnerabilitySeeds(
								$summaryBehaviour[ 'definition_key' ],
								$this->scanSource->vulnerabilitySection( $summaryBehaviour[ 'vulnerability_section' ] ),
								$item
							)
						);
					}
					continue 2;

				case 'maintenance':
					if ( $maintenanceItemsByGroupKey === null ) {
						$maintenanceItemsByGroupKey = $this->groupMaintenanceItemsByGroupKey(
							$this->maintenanceSource->itemsForBucket( $bucketSource, $bucketKey )
						);
					}
					$groupKey = $this->groupDefinitions->reviewMaintenanceGroupKeyForItemKey( $item[ 'key' ] );
					if ( isset( $maintenanceGroupsBuilt[ $groupKey ] ) ) {
						continue 2;
					}
					if ( isset( $maintenanceItemsByGroupKey[ $groupKey ] ) ) {
						$seeds[] = $this->maintenanceSeedBuilder->build(
							$groupKey,
							$maintenanceItemsByGroupKey[ $groupKey ]
						);
						$maintenanceGroupsBuilt[ $groupKey ] = true;
					}
					continue 2;
			}

			$this->mergeAttentionSeed( $seeds, $definitionKey, $item );
		}

		return \array_values( \array_filter( $seeds, static fn( array $seed ) :bool => $seed[ 'label' ] !== '' ) );
	}

	/**
	 * @param array<int|string,GroupSeed> $seeds
	 * @phpstan-param AttentionItem $item
	 */
	private function mergeAttentionSeed( array &$seeds, string $definitionKey, array $item ) :void {
		$seedKey = $definitionKey;
		if ( !isset( $seeds[ $seedKey ] ) ) {
			$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );
			$seeds[ $seedKey ] = [
				'key'              => $definitionKey,
				'is_healthy'       => false,
				'definition_key'   => $definitionKey,
				'label'            => $definition[ 'label' ],
				'item_count'       => 0,
				'status'           => 'good',
				'narrative'        => '',
				'detail_shell'     => $definition[ 'detail_shell' ],
				'links'            => [],
				'management_link'  => [],
				'detail_table'     => [],
				'attention_items'  => [],
				'maintenance_rows' => [],
				'summary_row'      => [],
			];
		}

		$seeds[ $seedKey ][ 'item_count' ] += $item[ 'count' ];
		$seeds[ $seedKey ][ 'status' ] = StatusPriority::highest( [
			$seeds[ $seedKey ][ 'status' ],
			$item[ 'severity' ],
		], 'good' );
		$seeds[ $seedKey ][ 'attention_items' ][] = $item;
	}

	/**
	 * @phpstan-param AttentionItem $item
	 * @return list<GroupSeed>
	 */
	private function buildAssetSeeds( string $definitionKey, string $assetSource, array $item ) :array {
		$summaries = $this->scanSource->activeAssetSummariesForSource( $assetSource );
		$seeds = [];

		foreach ( $summaries as $summary ) {
			$fileCount = \max( 0, (int)( $summary[ 'count_badge' ] ?? 0 ) );
			if ( $fileCount < 1 ) {
				continue;
			}

			$seeds[] = $this->buildScopedAssetSeed(
				$definitionKey,
				$summary,
				StatusPriority::normalize( $item[ 'severity' ], 'warning' ),
				$this->queueScanResultsOptions->buildSubjectActionData(
					$summary[ 'subject_type' ],
					$summary[ 'subject_id' ]
				),
				[ $item ]
			);
		}

		return $seeds;
	}

	/**
	 * @param list<AttentionItem> $pluginAttentionItems
	 * @return list<GroupSeed>
	 */
	private function buildPluginAssetSeeds( array $pluginAttentionItems ) :array {
		$activeItem = null;
		$ignoredItem = null;

		foreach ( $pluginAttentionItems as $item ) {
			if ( $item[ 'key' ] === 'plugin_files' ) {
				$activeItem = $item;
			}
			elseif ( $item[ 'key' ] === 'plugin_files_ignored' ) {
				$ignoredItem = $item;
			}
		}

		$seeds = [];
		if ( $activeItem !== null ) {
			foreach ( $this->scanSource->activeAssetSummariesForSource( 'plugins' ) as $summary ) {
				$fileCount = \max( 0, (int)( $summary[ 'count_badge' ] ?? 0 ) );
				if ( $fileCount < 1 ) {
					continue;
				}

				$seeds[] = $this->buildScopedAssetSeed(
					'plugins',
					$summary,
					StatusPriority::normalize( $activeItem[ 'severity' ], 'warning' ),
					$this->queueScanResultsOptions->buildSubjectActionData(
						$summary[ 'subject_type' ],
						$summary[ 'subject_id' ]
					),
					[ $activeItem ]
				);
			}
		}

		if ( $ignoredItem !== null ) {
			foreach ( $this->scanSource->fullyIgnoredPluginSummaries() as $summary ) {
				$fileCount = \max( 0, (int)( $summary[ 'count_badge' ] ?? 0 ) );
				if ( $fileCount < 1 ) {
					continue;
				}

				$seeds[] = $this->buildScopedAssetSeed(
					'plugins',
					$summary,
					StatusPriority::normalize( $ignoredItem[ 'severity' ], 'warning' ),
					$this->queueScanResultsOptions->buildSubjectActionData(
						$summary[ 'subject_type' ],
						$summary[ 'subject_id' ],
						$this->queueScanResultsOptions->forcedIgnoredOptions()
					),
					[ $ignoredItem ]
				);
			}
		}

		return $seeds;
	}

	/**
	 * @phpstan-param AttentionItem $item
	 * @return list<GroupSeed>
	 */
	private function buildVulnerabilitySeeds( string $definitionKey, array $section, array $item ) :array {
		if ( $section === [] ) {
			return [];
		}

		$seeds = [];
		foreach ( $section[ 'items' ] as $vulnerabilityItem ) {
			$seeds[] = [
				'key'              => $definitionKey.':'.$vulnerabilityItem[ 'key' ],
				'is_healthy'       => false,
				'definition_key'   => $definitionKey,
				'label'            => $vulnerabilityItem[ 'label' ],
				'item_count'       => $vulnerabilityItem[ 'count' ],
				'status'           => StatusPriority::normalize( $vulnerabilityItem[ 'severity' ], 'warning' ),
				'narrative'        => $vulnerabilityItem[ 'description' ],
				'detail_shell'     => 'direct_table',
				'links'            => $this->buildGroupLinksFromVulnerabilityActions( $vulnerabilityItem[ 'actions' ] ),
				'management_link'  => [],
				'detail_table'     => [],
				'attention_items'  => [ $item ],
				'maintenance_rows' => [],
				'summary_row'      => [],
			];
		}

		return $seeds;
	}

	/**
	 * @param QueueAssetSummaryRecord $summary
	 * @param list<AttentionItem> $attentionItems
	 * @return GroupSeed
	 */
	private function buildScopedAssetSeed(
		string $definitionKey,
		array $summary,
		string $status,
		array $renderActionData,
		array $attentionItems
	) :array {
		$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );

		return [
			'key'              => $definitionKey.':'.$summary[ 'key' ],
			'is_healthy'       => false,
			'definition_key'   => $definitionKey,
			'label'            => $summary[ 'title' ],
			'item_count'       => \max( 0, (int)( $summary[ 'count_badge' ] ?? 0 ) ),
			'status'           => $status,
			'narrative'        => $summary[ 'stat_text' ],
			'detail_shell'     => 'direct_table',
			'links'            => [],
			'management_link'  => [],
			'detail_table'     => [],
			'render_action_class_override' => ActionsQueueAssetFileStatusDetail::class,
			'render_action_data_override'  => $renderActionData,
			'attention_items'  => $attentionItems,
			'maintenance_rows' => [],
			'summary_row'      => [],
		];
	}

	/**
	 * @param list<VulnerabilityAction> $actions
	 * @return list<GroupLink>
	 */
	private function buildGroupLinksFromVulnerabilityActions( array $actions ) :array {
		$links = [];
		foreach ( $actions as $action ) {
			$label = \trim( (string)( $action[ 'label' ] ?? '' ) );
			$href = \trim( (string)( $action[ 'href' ] ?? '' ) );
			if ( $label === '' || $href === '' ) {
				continue;
			}

			$attributes = \is_array( $action[ 'attributes' ] ?? null ) ? $action[ 'attributes' ] : [];
			$target = \trim( (string)( $attributes[ 'target' ] ?? '' ) );
			$links[] = [
				'label'      => $label,
				'href'       => $href,
				'target'     => $target,
				'rel'        => $target === '_blank' ? 'noopener noreferrer' : '',
				'icon_class' => $target === '_blank' ? 'bi-box-arrow-up-right' : '',
			];
		}
		return $links;
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @return array<string,list<MaintenanceQueueItem>>
	 */
	private function groupMaintenanceItemsByGroupKey( array $maintenanceItems ) :array {
		$grouped = [];

		foreach ( $maintenanceItems as $item ) {
			$grouped[ $this->groupDefinitions->reviewMaintenanceGroupKeyForItemKey( $item[ 'key' ] ) ][] = $item;
		}

		return $grouped;
	}
}
