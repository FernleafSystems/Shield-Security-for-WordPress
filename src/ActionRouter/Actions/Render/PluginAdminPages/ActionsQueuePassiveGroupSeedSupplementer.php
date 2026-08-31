<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\GetPendingFileLockDisplays;

/**
 * @phpstan-import-type BucketSource from ActionsQueueBucketsBuilder
 * @phpstan-import-type GroupSeed from ActionsQueueGroupContractBuilder
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 */
class ActionsQueuePassiveGroupSeedSupplementer {

	private ActionsQueueGroupDefinitions $groupDefinitions;
	private ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder;
	private ActionsQueueGroupMaintenanceSource $maintenanceSource;
	private ?GetPendingFileLockDisplays $pendingFileLockDisplays;
	private ActionsQueueScanResultScopeStateBuilder $scanResultScopeStateBuilder;
	private ActionsQueueScanResultScopeResolver $scanResultScopeResolver;
	private ScansResultsRailTabAvailability $scanAvailability;

	public function __construct(
		ActionsQueueGroupDefinitions $groupDefinitions,
		ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder,
		ActionsQueueGroupMaintenanceSource $maintenanceSource,
		?GetPendingFileLockDisplays $pendingFileLockDisplays = null,
		?ActionsQueueScanResultScopeStateBuilder $scanResultScopeStateBuilder = null,
		?ActionsQueueScanResultScopeResolver $scanResultScopeResolver = null,
		?ScansResultsRailTabAvailability $scanAvailability = null
	) {
		$this->groupDefinitions = $groupDefinitions;
		$this->maintenanceSeedBuilder = $maintenanceSeedBuilder;
		$this->maintenanceSource = $maintenanceSource;
		$this->pendingFileLockDisplays = $pendingFileLockDisplays;
		$this->scanResultScopeStateBuilder = $scanResultScopeStateBuilder ?? new ActionsQueueScanResultScopeStateBuilder();
		$this->scanResultScopeResolver = $scanResultScopeResolver ?? new ActionsQueueScanResultScopeResolver();
		$this->scanAvailability = $scanAvailability ?? new ScansResultsRailTabAvailability();
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

		foreach ( $this->buildDisabledScanSeedsForBucket( $bucketKey, $bucketSource ) as $seed ) {
			if ( isset( $existingGroupKeys[ $seed[ 'key' ] ] ) ) {
				continue;
			}
			$seeds[] = $seed;
			$existingGroupKeys[ $seed[ 'key' ] ] = true;
		}

		foreach ( $this->buildHealthyScanSeedsForBucket( $bucketKey, $bucketSource, $assessmentRowsByZone[ 'scans' ] ?? [] ) as $seed ) {
			if ( isset( $existingGroupKeys[ $seed[ 'key' ] ] ) ) {
				continue;
			}
			$seeds[] = $seed;
			$existingGroupKeys[ $seed[ 'key' ] ] = true;
		}

		foreach ( $this->buildIgnoredOnlyDirectScanSeedsForBucket( $bucketKey ) as $seed ) {
			if ( isset( $existingGroupKeys[ $seed[ 'key' ] ] ) ) {
				continue;
			}
			$seeds[] = $seed;
			$existingGroupKeys[ $seed[ 'key' ] ] = true;
		}

		foreach ( $this->groupHealthyMaintenanceItemsByGroupKey(
			$this->maintenanceSource->itemsForBucket( $bucketSource, $bucketKey ),
			$bucketKey
		) as $groupKey => $maintenanceItems ) {
			$hasActiveBaseGroup = isset( $existingGroupKeys[ $groupKey ] );
			$useHealthyCompanionKey = $hasActiveBaseGroup
				&& $this->groupDefinitions->isReviewMaintenanceAggregateGroupKey( $groupKey );
			if ( $hasActiveBaseGroup && !$useHealthyCompanionKey ) {
				continue;
			}

			$seed = $this->maintenanceSeedBuilder->build(
				$groupKey,
				$maintenanceItems,
				true,
				$useHealthyCompanionKey
			);
			if ( isset( $existingGroupKeys[ $seed[ 'key' ] ] ) ) {
				continue;
			}

			$seeds[] = $seed;
			$existingGroupKeys[ $seed[ 'key' ] ] = true;
		}

		return $seeds;
	}

	/**
	 * @return list<GroupSeed>
	 */
	private function buildIgnoredOnlyDirectScanSeedsForBucket( string $bucketKey ) :array {
		if ( $bucketKey !== 'critical' ) {
			return [];
		}

		$seeds = [];
		foreach ( $this->groupDefinitions->ignoredOnlyDirectTableGroupKeys() as $definitionKey ) {
			$availability = $this->scanAvailability->build( $definitionKey );
			if ( empty( $availability[ 'is_available' ] ) || empty( $availability[ 'show_in_actions_queue' ] ) ) {
				continue;
			}

			try {
				$scope = $this->scanResultScopeResolver->resolveForGroup( $definitionKey );
				if ( empty( $scope ) ) {
					continue;
				}
				$counts = $this->scanResultScopeStateBuilder->buildCountsForActionScope(
					$scope[ 'type' ],
					$scope[ 'file' ]
				);
			}
			catch ( \InvalidArgumentException $e ) {
				continue;
			}

			if ( (int)$counts[ 'active_count' ] !== 0 || (int)$counts[ 'ignored_count' ] < 1 ) {
				continue;
			}

			$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );
			$seeds[] = [
				'key'                         => $definitionKey,
				'definition_key'              => $definitionKey,
				'label'                       => $definition[ 'label' ],
				'item_count'                  => 0,
				'status'                      => 'good',
				'narrative'                   => __( 'No active results remain in this group.', 'wp-simple-firewall' ),
				'detail_shell'                => $definition[ 'detail_shell' ],
				'links'                       => [],
				'management_link'             => [],
				'is_interactive_override'     => true,
				'detail_table'                => [],
				'render_action_data_override' => $definition[ 'render_action_data' ],
				'attention_items'             => [],
				'maintenance_rows'            => [],
				'summary_row'                 => [],
				'context_actions_override'    => [],
			];
		}

		return $seeds;
	}

	/**
	 * @return list<GroupSeed>
	 */
	private function buildDisabledScanSeedsForBucket( string $bucketKey, array $bucketSource ) :array {
		if ( $bucketKey !== 'critical' ) {
			return [];
		}

		$seeds = [];
		foreach ( $bucketSource[ 'disabled_groups' ] as $definitionKey => $availability ) {
			$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );
			$isFileLockerSetup = $definitionKey === 'file_locker'
				&& $availability[ 'disabled_reason' ] === 'not_enabled';
			$statusLabel = $availability[ 'disabled_reason' ] === 'upgrade_required'
				? __( 'Upgrade Required', 'wp-simple-firewall' )
				: __( 'Not Enabled', 'wp-simple-firewall' );

			$seeds[] = [
				'key'                     => $definitionKey,
				'definition_key'          => $definitionKey,
				'label'                   => $definition[ 'label' ],
				'item_count'              => 0,
				'status'                  => 'neutral',
				'narrative'               => $availability[ 'disabled_message' ],
				'detail_shell'            => $definition[ 'detail_shell' ],
				'links'                   => [],
				'management_link'         => [],
				'is_interactive_override' => true,
				'detail_table'            => [],
				'attention_items'         => [],
				'maintenance_rows'        => [],
				'summary_row'             => [],
				'status_label_override'   => $statusLabel,
				'header_summary_override' => $availability[ 'disabled_message' ],
				'header_focus_override'   => $availability[ 'disabled_reason' ] === 'upgrade_required'
					? __( 'Upgrade your plan to unlock this protection.', 'wp-simple-firewall' )
					: ( $isFileLockerSetup
						? __( 'Enable File Locker for the files you want to protect.', 'wp-simple-firewall' )
						: __( 'Open this protection\'s settings to switch it on.', 'wp-simple-firewall' ) ),
				'header_next_step_override' => $availability[ 'disabled_reason' ] === 'upgrade_required'
					? __( 'Review the upgrade option for this protection.', 'wp-simple-firewall' )
					: ( $isFileLockerSetup
						? __( 'Open File Locker to enable protection for individual files.', 'wp-simple-firewall' )
						: __( 'Review this protection\'s settings and switch it on.', 'wp-simple-firewall' ) ),
				'header_badge_override'      => $statusLabel,
				'header_badge_status_override' => 'neutral',
				'header_color_key_override'    => 'neutral',
				'context_actions_override'     => [],
			];
			if ( \in_array( $definitionKey, [ 'vulnerabilities', 'abandoned' ], true ) ) {
				$seeds[ \array_key_last( $seeds ) ][ 'card_type_override' ] = 'expandable';
			}
		}

		return $seeds;
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 * @param list<AssessmentRow> $assessmentRows
	 * @return list<GroupSeed>
	 */
	private function buildHealthyScanSeedsForBucket( string $bucketKey, array $bucketSource, array $assessmentRows ) :array {
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
			$interaction = $this->buildHealthyScanInteraction(
				$definitionKey,
				$definitionKey === 'hidden_plugins'
					? $this->hasUsefulDetail( $rows )
					: null
			);
			$seed = [
				'key'                         => $definitionKey,
				'definition_key'              => $definitionKey,
				'label'                       => $definition[ 'label' ],
				'item_count'                  => $interaction[ 'item_count_override' ],
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
			if ( $definitionKey === 'file_locker' ) {
				$pendingFileLockerCount = $this->getPendingFileLockerCount();
				if ( $pendingFileLockerCount > 0 ) {
					$pendingFileLockerState = $this->describePendingFileLockerState( $pendingFileLockerCount );
					$pendingStatusLabel = __( 'Pending', 'wp-simple-firewall' );
					$seed = \array_merge(
						$seed,
						[
							'status'                        => 'neutral',
							'narrative'                     => $pendingFileLockerState,
							'status_label_override'         => $pendingStatusLabel,
							'header_summary_override'       => $pendingFileLockerState,
							'header_focus_override'         => \sprintf(
								_n(
									'%s protected file is still waiting for its first lock.',
									'%s protected files are still waiting for their first lock.',
									$pendingFileLockerCount,
									'wp-simple-firewall'
								),
								$pendingFileLockerCount
							),
							'header_next_step_override'     => __( 'Open this view to monitor the files still waiting for their first lock.', 'wp-simple-firewall' ),
							'header_badge_override'         => $pendingStatusLabel,
							'header_badge_status_override'  => 'neutral',
							'header_color_key_override'     => 'neutral',
						]
					);
				}
			}
			if ( $interaction[ 'suppress_context_actions' ] ) {
				$seed[ 'context_actions_override' ] = [];
			}
			if ( $interaction[ 'suppress_detail_render_action_if_noninteractive' ] ) {
				$seed[ 'suppress_detail_render_action_if_noninteractive' ] = true;
			}
			$seeds[] = $seed;
		}

		return $seeds;
	}

	private function getPendingFileLockerCount() :int {
		return $this->pendingFileLockDisplays()->count();
	}

	private function describePendingFileLockerState( int $pendingFileLockerCount ) :string {
		return $this->pendingFileLockDisplays()->describeCount( $pendingFileLockerCount );
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
	 *   item_count_override:int,
	 *   render_action_data:array<string,mixed>,
	 *   suppress_context_actions:bool,
	 *   suppress_detail_render_action_if_noninteractive:bool
	 * }
	 */
	private function buildHealthyScanInteraction( string $definitionKey, ?bool $hasUsefulDetail ) :array {
		if ( $definitionKey === 'hidden_plugins' && $hasUsefulDetail === false ) {
			return [
				'is_interactive'                               => false,
				'item_count_override'                          => 0,
				'render_action_data'                           => [],
				'suppress_context_actions'                     => true,
				'suppress_detail_render_action_if_noninteractive' => true,
			];
		}

		$interactionMode = $this->groupDefinitions->healthyInteractionModeForGroupKey( $definitionKey );
		if ( $interactionMode === 'default_detail' ) {
			return [
				'is_interactive'                               => true,
				'item_count_override'                          => 0,
				'render_action_data'                           => $this->groupDefinitions->definitionForGroupKey( $definitionKey )[ 'render_action_data' ],
				'suppress_context_actions'                     => false,
				'suppress_detail_render_action_if_noninteractive' => false,
			];
		}

		return [
			'is_interactive'                               => false,
			'item_count_override'                          => 0,
			'render_action_data'                           => [],
			'suppress_context_actions'                     => true,
			'suppress_detail_render_action_if_noninteractive' => false,
		];
	}

	/**
	 * @param list<AssessmentRow> $rows
	 */
	private function hasUsefulDetail( array $rows ) :?bool {
		foreach ( $rows as $row ) {
			if ( \array_key_exists( 'has_useful_detail', $row ) ) {
				return (bool)$row[ 'has_useful_detail' ];
			}
		}

		return null;
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @return array<string,list<MaintenanceQueueItem>>
	 */
	private function groupHealthyMaintenanceItemsByGroupKey( array $maintenanceItems, string $bucketKey ) :array {
		$grouped = [];

		foreach ( $maintenanceItems as $maintenanceItem ) {
			if ( $maintenanceItem[ 'severity' ] !== 'good'
				|| $maintenanceItem[ 'drill_bucket' ] !== $bucketKey ) {
				continue;
			}

			$grouped[ $this->groupDefinitions->reviewMaintenanceGroupKeyForItemKey(
				$maintenanceItem[ 'key' ]
			) ][] = $maintenanceItem;
		}

		return $grouped;
	}

	private function pendingFileLockDisplays() :GetPendingFileLockDisplays {
		if ( $this->pendingFileLockDisplays === null ) {
			$this->pendingFileLockDisplays = new GetPendingFileLockDisplays();
		}

		return $this->pendingFileLockDisplays;
	}
}
