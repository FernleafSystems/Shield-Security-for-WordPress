<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type BucketSource from ActionsQueueBucketsBuilder
 * @phpstan-import-type GroupSeed from ActionsQueueGroupContractBuilder
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 */
class ActionsQueueHealthyGroupSeedSupplementer {

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

		foreach ( $this->buildHealthyScanSeedsForBucket( $bucketKey, $assessmentRowsByZone[ 'scans' ] ?? [] ) as $seed ) {
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
			if ( $definitionKey === 'plugins' && $this->scanSource->fullyIgnoredPluginSummaries() !== [] ) {
				continue;
			}

			$definition = $this->groupDefinitions->definitionForGroupKey( $definitionKey );
			$interaction = $this->buildHealthyScanInteraction( $definitionKey );
			$seeds[] = [
				'key'                         => $definitionKey,
				'is_healthy'                  => true,
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
	 *   item_count_override:int|null,
	 *   render_action_data:array<string,mixed>
	 * }
	 */
	private function buildHealthyScanInteraction( string $definitionKey ) :array {
		$interactionMode = $this->groupDefinitions->healthyInteractionModeForGroupKey( $definitionKey );
		if ( $interactionMode === 'default_detail' ) {
			return [
				'is_interactive'      => true,
				'item_count_override' => 0,
				'render_action_data'  => $this->groupDefinitions->definitionForGroupKey( $definitionKey )[ 'render_action_data' ],
			];
		}

		if ( $interactionMode !== 'ignored_only' ) {
			return [
				'is_interactive'      => false,
				'item_count_override' => null,
				'render_action_data'  => [],
			];
		}

		$ignoredCount = $this->scanSource->ignoredCountForSource(
			$this->groupDefinitions->healthyIgnoredSourceForGroupKey( $definitionKey )
		);

		return [
			'is_interactive'      => $ignoredCount > 0,
			'item_count_override' => $ignoredCount > 0 ? $ignoredCount : null,
			'render_action_data'  => $this->groupDefinitions->ignoredRenderActionDataForGroupKey( $definitionKey, $ignoredCount ),
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
}
