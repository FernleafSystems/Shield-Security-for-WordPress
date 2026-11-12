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

	private const PRO_UPSELL_GROUP_KEYS = [
		'malware',
		'vulnerabilities',
		'plugins',
		'themes',
		'file_locker',
	];

	private ActionsQueueGroupDefinitions $groupDefinitions;
	private ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder;
	private ActionsQueueGroupMaintenanceSource $maintenanceSource;
	private ActionsQueueGroupScanSource $scanSource;
	private ?GetPendingFileLockDisplays $pendingFileLockDisplays;
	private ActionsQueueScanResultScopeStateBuilder $scanResultScopeStateBuilder;
	private ActionsQueueScanResultScopeResolver $scanResultScopeResolver;
	private ScansResultsRailTabAvailability $scanAvailability;

	public function __construct(
		ActionsQueueGroupDefinitions $groupDefinitions,
		ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder,
		ActionsQueueGroupMaintenanceSource $maintenanceSource,
		ActionsQueueGroupScanSource $scanSource,
		?GetPendingFileLockDisplays $pendingFileLockDisplays = null,
		?ActionsQueueScanResultScopeStateBuilder $scanResultScopeStateBuilder = null,
		?ActionsQueueScanResultScopeResolver $scanResultScopeResolver = null,
		?ScansResultsRailTabAvailability $scanAvailability = null
	) {
		$this->groupDefinitions = $groupDefinitions;
		$this->maintenanceSeedBuilder = $maintenanceSeedBuilder;
		$this->maintenanceSource = $maintenanceSource;
		$this->scanSource = $scanSource;
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

		foreach ( $this->buildDisabledScanSeedsForBucket( $bucketSource ) as $seed ) {
			if ( isset( $existingGroupKeys[ $seed[ 'key' ] ] ) ) {
				continue;
			}
			$seeds[] = $seed;
			$existingGroupKeys[ $seed[ 'key' ] ] = true;
		}

		foreach ( $this->buildHealthyScanSeedsForBucket( $bucketKey, $assessmentRowsByZone[ 'scans' ] ?? [] ) as $seed ) {
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
				'management_link'             => [],
				'is_interactive_override'     => true,
				'render_action_data_override' => $definition[ 'render_action_data' ],
				'attention_items'             => [],
				'maintenance_rows'            => [],
				'summary_row'                 => [],
			];
		}

		return $seeds;
	}

	/**
	 * @return list<GroupSeed>
	 */
	private function buildDisabledScanSeedsForBucket( array $bucketSource ) :array {
		$seeds = [];
		foreach ( $bucketSource[ 'disabled_groups' ] as $definitionKey => $availability ) {
			$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );
			$isFileLockerSetup = $definitionKey === 'file_locker'
				&& $availability[ 'disabled_reason' ] === 'not_enabled';
			$statusLabel = $availability[ 'disabled_reason' ] === 'upgrade_required'
				? __( 'Upgrade Required', 'wp-simple-firewall' )
				: ( $isFileLockerSetup ? __( 'Not all locked', 'wp-simple-firewall' ) : __( 'Not Enabled', 'wp-simple-firewall' ) );

			$seed = [
				'key'                     => $definitionKey,
				'definition_key'          => $definitionKey,
				'label'                   => $definition[ 'label' ],
				'item_count'              => 0,
				// Critical files left unlocked are a warning to review.
				'status'                  => $isFileLockerSetup ? 'warning' : 'neutral',
				'narrative'               => $availability[ 'disabled_message' ],
				'detail_shell'            => $definition[ 'detail_shell' ],
				'management_link'         => [],
				'is_interactive_override' => true,
				'attention_items'         => [],
				'maintenance_rows'        => [],
				'summary_row'             => [],
				'status_label_override'   => $statusLabel,
				'header_summary_override' => $availability[ 'disabled_message' ],
				'header_focus_override'   => $availability[ 'disabled_reason' ] === 'upgrade_required'
					? __( 'Upgrade your plan to unlock this protection.', 'wp-simple-firewall' )
					: ( $isFileLockerSetup
						? __( 'Lock each critical file so any change to it is caught.', 'wp-simple-firewall' )
						: __( 'Open this protection\'s settings to switch it on.', 'wp-simple-firewall' ) ),
				'header_next_step_override' => $availability[ 'disabled_reason' ] === 'upgrade_required'
					? __( 'Review the upgrade option for this protection.', 'wp-simple-firewall' )
					: ( $isFileLockerSetup
						? __( 'Open File Locker and lock the files that are not locked yet.', 'wp-simple-firewall' )
						: __( 'Review this protection\'s settings and switch it on.', 'wp-simple-firewall' ) ),
				'header_badge_override'      => $statusLabel,
				'header_badge_status_override' => 'neutral',
				'header_color_key_override'    => 'neutral',
			];
			if ( $availability[ 'disabled_reason' ] === 'upgrade_required'
				&& \in_array( $definitionKey, self::PRO_UPSELL_GROUP_KEYS, true ) ) {
				$seed[ 'is_pro_upsell' ] = true;
			}
			if ( $availability[ 'disabled_reason' ] === 'not_enabled' && !$isFileLockerSetup ) {
				$seed[ 'enable_dialog_json' ] = ( new ProtectionEnableDialogBuilder() )->forScan( $definitionKey, $definition[ 'icon_class' ] );
			}
			$seeds[] = $seed;
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
			$seed = [
				'key'                         => $definitionKey,
				'definition_key'              => $definitionKey,
				'label'                       => $definition[ 'label' ],
				'item_count'                  => 0,
				'status'                      => 'good',
				'narrative'                   => $this->combineHealthyAssessmentNarratives( $rows ),
				'detail_shell'                => $definition[ 'detail_shell' ],
				'management_link'             => [],
				'is_interactive_override'     => $interaction[ 'is_interactive' ],
				'render_action_data_override' => $interaction[ 'render_action_data' ],
				'attention_items'             => [],
				'maintenance_rows'            => [],
				'summary_row'                 => [],
			];
			if ( $definitionKey === 'abandoned' ) {
				// Everything here may be ignored, and each still needs its way back.
				$seed[ 'ignored_rows' ] = ( new ActionsQueueCompactSummaryRowBuilder() )->buildForAssets(
					$this->scanSource->vulnerabilitySection( 'abandoned' )[ 'ignored_items' ]
				);
			}
			if ( $definitionKey === 'hidden_plugins' ) {
				$seed[ 'ignored_rows' ] = $this->scanSource->cloakedPluginRows()[ 'ignored' ];
			}
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
	 *   render_action_data:array<string,mixed>
	 * }
	 */
	private function buildHealthyScanInteraction( string $definitionKey ) :array {
		$interactionMode = $this->groupDefinitions->healthyInteractionModeForGroupKey( $definitionKey );
		if ( $interactionMode === 'default_detail' ) {
			return [
				'is_interactive'     => true,
				'render_action_data' => $this->groupDefinitions->definitionForGroupKey( $definitionKey )[ 'render_action_data' ],
			];
		}

		return [
			'is_interactive'     => false,
			'render_action_data' => [],
		];
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
