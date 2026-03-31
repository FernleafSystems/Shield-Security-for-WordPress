<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type BucketSource from ActionsQueueBucketsBuilder
 * @phpstan-import-type GroupLink from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupSeed from ActionsQueueGroupContractBuilder
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
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

		foreach ( $bucketSource[ 'attention_items' ] as $item ) {
			$summaryBehaviour = $this->groupDefinitions->summaryBehaviourForKey( $item[ 'key' ] );
			$definitionKey = $summaryBehaviour[ 'definition_key' ];

			switch ( $summaryBehaviour[ 'seed_strategy' ] ) {
				case 'asset_cards':
					if ( $summaryBehaviour[ 'asset_source' ] === 'plugins' && !$pluginsExpanded ) {
						$pluginsExpanded = true;
						$seeds = \array_merge(
							$seeds,
							$this->buildAssetSeeds( 'plugins', $summaryBehaviour[ 'asset_source' ], $item )
						);
					}
					elseif ( $summaryBehaviour[ 'asset_source' ] === 'themes' && !$themesExpanded ) {
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
				'heading_label'    => $definition[ 'label' ],
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
		$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );
		$summaries = $this->scanSource->activeAssetSummariesForSource( $assetSource );
		$seeds = [];

		foreach ( $summaries as $summary ) {
			$fileCount = \max( 0, (int)( $summary[ 'count_badge' ] ?? 0 ) );
			if ( $fileCount < 1 ) {
				continue;
			}

			$seeds[] = [
				'key'              => $definitionKey.':'.$summary[ 'key' ],
				'is_healthy'       => false,
				'definition_key'   => $definitionKey,
				'heading_label'    => $definition[ 'label' ],
				'label'            => $summary[ 'title' ],
				'item_count'       => $fileCount,
				'status'           => StatusPriority::normalize( $item[ 'severity' ], 'warning' ),
				'narrative'        => $summary[ 'stat_text' ],
				'detail_shell'     => 'direct_table',
				'links'            => [],
				'management_link'  => [],
				'detail_table'     => [],
				'render_action_class_override' => ActionsQueueAssetFileStatusDetail::class,
				'render_action_data_override'  => [
					'subject_type'            => $summary[ 'subject_type' ],
					'subject_id'              => $summary[ 'subject_id' ],
					'results_display_options' => $this->queueScanResultsOptions->activeOnly(),
				],
				'attention_items'  => [ $item ],
				'maintenance_rows' => [],
				'summary_row'      => [],
			];
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
				'heading_label'    => $section[ 'label' ],
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
