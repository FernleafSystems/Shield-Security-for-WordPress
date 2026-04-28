<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\GetPendingFileLockDisplays;

/**
 * @phpstan-import-type BucketSource from ActionsQueueBucketsBuilder
 * @phpstan-import-type GroupSeed from ActionsQueueGroupContractBuilder
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 */
class ActionsQueuePassiveGroupSeedSupplementer {

	private ActionsQueueGroupDefinitions $groupDefinitions;
	private ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder;
	private ActionsQueueGroupScanSource $scanSource;
	private ActionsQueueGroupMaintenanceSource $maintenanceSource;

	public function __construct(
		ActionsQueueGroupDefinitions $groupDefinitions,
		ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder,
		ActionsQueueGroupScanSource $scanSource,
		ActionsQueueGroupMaintenanceSource $maintenanceSource
	) {
		$this->groupDefinitions = $groupDefinitions;
		$this->maintenanceSeedBuilder = $maintenanceSeedBuilder;
		$this->scanSource = $scanSource;
		$this->maintenanceSource = $maintenanceSource;
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

		foreach ( $this->buildHealthyScanSeedsForBucket( $bucketKey, $bucketSource, $assessmentRowsByZone[ 'scans' ] ?? [] ) as $seed ) {
			if ( isset( $existingGroupKeys[ $seed[ 'key' ] ] ) ) {
				continue;
			}
			$seeds[] = $seed;
			$existingGroupKeys[ $seed[ 'key' ] ] = true;
		}

		foreach ( $this->buildDisabledScanSeedsForBucket( $bucketKey, $bucketSource ) as $seed ) {
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
			if ( isset( $existingGroupKeys[ $groupKey ] ) ) {
				continue;
			}

			$seeds[] = $this->maintenanceSeedBuilder->build( $groupKey, $maintenanceItems, true );
			$existingGroupKeys[ $groupKey ] = true;
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
					? __( 'Upgrade this plan to unlock this protection.', 'wp-simple-firewall' )
					: __( 'Open settings to switch on this protection.', 'wp-simple-firewall' ),
				'header_next_step_override' => $availability[ 'disabled_reason' ] === 'upgrade_required'
					? __( 'Use the action below to review the upgrade path for this protection.', 'wp-simple-firewall' )
					: __( 'Use the action below to open the relevant settings and switch this protection on.', 'wp-simple-firewall' ),
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
		$pendingFileLockerCount = $this->getPendingFileLockerCount();
		foreach ( $rowsByDefinitionKey as $definitionKey => $rows ) {
			if ( $this->bucketHasIgnoredOnlyAttentionForDefinition( $bucketSource, $definitionKey ) ) {
				continue;
			}

			$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );
			$interaction = $this->buildHealthyScanInteraction( $definitionKey );
			$seed = [
				'key'                         => $definitionKey,
				'definition_key'              => $definitionKey,
				'label'                       => $definition[ 'label' ],
				'item_count'                  => $interaction[ 'item_count_override' ] ?? \count( $rows ),
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
			if ( $definitionKey === 'file_locker' && $pendingFileLockerCount > 0 ) {
				$seed = \array_merge(
					$seed,
					[
						'status'                        => 'neutral',
						'narrative'                     => $this->describePendingFileLockerState( $pendingFileLockerCount ),
						'status_label_override'         => __( 'Pending', 'wp-simple-firewall' ),
						'header_summary_override'       => $this->describePendingFileLockerState( $pendingFileLockerCount ),
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
						'header_badge_override'         => __( 'Pending', 'wp-simple-firewall' ),
						'header_badge_status_override'  => 'neutral',
						'header_color_key_override'     => 'neutral',
					]
				);
			}
			if ( $interaction[ 'suppress_context_actions' ] ) {
				$seed[ 'context_actions_override' ] = [];
			}
			$seeds[] = $seed;
		}

		return $seeds;
	}

	protected function getPendingFileLockerCount() :int {
		return $this->pendingFileLockDisplays()->count();
	}

	private function describePendingFileLockerState( int $pendingFileLockerCount ) :string {
		return $this->pendingFileLockDisplays()->describeCount( $pendingFileLockerCount );
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 */
	private function bucketHasIgnoredOnlyAttentionForDefinition( array $bucketSource, string $definitionKey ) :bool {
		foreach ( $bucketSource[ 'attention_items' ] as $item ) {
			$itemKey = (string)( $item[ 'key' ] ?? '' );
			if ( ActionsQueueGroupDefinitions::isIgnoredOnlySummaryKey( $itemKey )
				&& $this->groupDefinitions->groupKeyForSummaryKey( $itemKey ) === $definitionKey ) {
				return true;
			}
		}

		return false;
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
	 *   item_count_override:int|null,
	 *   render_action_data:array<string,mixed>,
	 *   suppress_context_actions:bool
	 * }
	 */
	private function buildHealthyScanInteraction( string $definitionKey ) :array {
		$interactionMode = $this->groupDefinitions->healthyInteractionModeForGroupKey( $definitionKey );
		if ( $interactionMode === 'default_detail' ) {
			return [
				'is_interactive'      => true,
				'item_count_override' => 0,
				'render_action_data'  => $this->groupDefinitions->definitionForGroupKey( $definitionKey )[ 'render_action_data' ],
				'suppress_context_actions' => false,
			];
		}

		if ( $interactionMode !== 'ignored_only' ) {
			return [
				'is_interactive'      => false,
				'item_count_override' => null,
				'render_action_data'  => [],
				'suppress_context_actions' => false,
			];
		}

		$ignoredCount = $this->scanSource->ignoredCountForSource(
			$this->groupDefinitions->healthyIgnoredSourceForGroupKey( $definitionKey )
		);

		return [
			'is_interactive'      => $ignoredCount > 0,
			'item_count_override' => $ignoredCount > 0 ? $ignoredCount : null,
			'render_action_data'  => $ignoredCount > 0
				? $this->groupDefinitions->definitionForGroupKey( $definitionKey )[ 'render_action_data' ]
				: [],
			'suppress_context_actions' => $ignoredCount > 0,
		];
	}

	/**
	 * @param list<array<string,mixed>> $maintenanceItems
	 * @return array<string,list<array<string,mixed>>>
	 */
	private function groupHealthyMaintenanceItemsByGroupKey( array $maintenanceItems, string $bucketKey ) :array {
		$grouped = [];

		foreach ( $maintenanceItems as $maintenanceItem ) {
			if ( ( $maintenanceItem[ 'severity' ] ?? '' ) !== 'good'
				|| ( $maintenanceItem[ 'drill_bucket' ] ?? '' ) !== $bucketKey ) {
				continue;
			}

			$grouped[ $this->groupDefinitions->reviewMaintenanceGroupKeyForItemKey(
				(string)$maintenanceItem[ 'key' ]
			) ][] = $maintenanceItem;
		}

		return $grouped;
	}

	private function pendingFileLockDisplays() :GetPendingFileLockDisplays {
		return new GetPendingFileLockDisplays();
	}
}
