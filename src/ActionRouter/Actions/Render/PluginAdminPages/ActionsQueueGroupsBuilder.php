<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;

/**
 * @phpstan-import-type AttentionItem from BuildAttentionItems
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type BucketData from ActionsQueueBucketsBuilder
 * @phpstan-import-type BucketSource from ActionsQueueBucketsBuilder
 * @phpstan-import-type ResolvedGroups from ActionsQueueGroupContractBuilder
 * @phpstan-import-type BucketSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type GroupSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type DrillLayerHeader from OperatorChromeContract
 * @phpstan-import-type CompactSummaryRow from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-type GroupLink array{
 *   label:string,
 *   href:string,
 *   target:string,
 *   rel:string,
 *   icon_class:string
 * }
 * @phpstan-type GroupManagementLink array{
 *   label:string,
 *   href:string,
 *   target:string,
 *   rel:string,
 *   icon_class:string
 * }
 * @phpstan-type GroupData array{
 *   key:string,
 *   label:string,
 *   item_count:int,
 *   status:string,
 *   status_label:string,
 *   icon_class:string,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   card_type:'expandable'|'linked'|'category',
 *   narrative:string,
 *   drill_hint:string,
 *   links:list<GroupLink>,
 *   management_link:array{}|GroupManagementLink,
 *   is_interactive:bool,
 *   detail_table:array<string,mixed>,
 *   render_action_class:class-string<BaseAction>,
 *   render_action_data:array<string,mixed>,
 *   maintenance_rows:list<CompactSummaryRow>,
 *   summary_row:array{}|CompactSummaryRow,
 *   selection:GroupSelection
 * }
 * @phpstan-type GroupSectionData array{
 *   heading_label:string,
 *   groups:list<GroupData>
 * }
 * @phpstan-type GroupsLayerData array{
 *   bucket_selection:BucketSelection,
 *   active_sections:list<GroupSectionData>,
 *   healthy_sections:list<GroupSectionData>,
 *   header:DrillLayerHeader
 * }
 * @phpstan-type ComputedGroups array{
 *   layer:GroupsLayerData,
 *   groups_indexed:array<string,GroupData>
 * }
 */
class ActionsQueueGroupsBuilder {

	private ?ActionsQueueGroupDefinitions $groupDefinitions = null;
	private ?ActionsQueueDrillDownPresentationBuilder $presentation = null;
	private ?ScanResultsDisplayOptions $queueScanResultsOptions = null;
	private ?ActionsQueueAssetMetadataResolver $assetMetadataResolver = null;
	private ?ActionsQueueMaintenanceGroupSeedBuilder $maintenanceSeedBuilder = null;
	private ?ActionsQueueGroupScanSource $groupScanSource = null;
	private ?ActionsQueueGroupMaintenanceSource $groupMaintenanceSource = null;
	private ?ActionsQueueBucketsBuilder $bucketsBuilder = null;
	private ?ActionsQueueGroupSeedCollector $seedCollector = null;
	private ?ActionsQueuePassiveGroupSeedSupplementer $passiveSeedSupplementer = null;
	private ?ActionsQueueGroupContractBuilder $contractBuilder = null;

	/**
	 * @param AttentionQuery $attentionQuery
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return GroupsLayerData
	 */
	public function build( string $bucketKey, array $attentionQuery, array $assessmentRowsByZone ) :array {
		return $this->compute( $bucketKey, $attentionQuery, $assessmentRowsByZone )[ 'layer' ];
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return GroupData
	 */
	public function buildGroup( string $bucketKey, string $groupKey, array $attentionQuery, array $assessmentRowsByZone ) :array {
		$computed = $this->compute( $bucketKey, $attentionQuery, $assessmentRowsByZone );
		return $computed[ 'groups_indexed' ][ $groupKey ]
			?? $this->contractBuilder()->buildEmptyGroup( $groupKey, $computed[ 'layer' ][ 'bucket_selection' ][ 'label' ] );
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return array{
	 *   layer:GroupsLayerData,
	 *   selected_group:GroupData
	 * }
	 */
	public function buildWithSelectedGroup( string $bucketKey, string $groupKey, array $attentionQuery, array $assessmentRowsByZone ) :array {
		$computed = $this->compute( $bucketKey, $attentionQuery, $assessmentRowsByZone );

		return [
			'layer'          => $computed[ 'layer' ],
			'selected_group' => $computed[ 'groups_indexed' ][ $groupKey ]
				?? $this->contractBuilder()->buildEmptyGroup( $groupKey, $computed[ 'layer' ][ 'bucket_selection' ][ 'label' ] ),
		];
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return ComputedGroups
	 */
	private function compute( string $bucketKey, array $attentionQuery, array $assessmentRowsByZone ) :array {
		$bucketsBuilder = $this->bucketsBuilder();
		$bucketBuild = $bucketsBuilder->buildWithSources( $attentionQuery, $assessmentRowsByZone );
		$buckets = $this->indexBucketsByKey( $bucketBuild[ 'buckets' ] );
		$bucketSources = $bucketBuild[ 'sources' ];
		$bucket = $buckets[ $bucketKey ];
		$resolvedGroups = $this->buildGroupsForBucket(
			$bucketKey,
			$bucket[ 'label' ],
			$bucketSources[ $bucketKey ],
			$assessmentRowsByZone
		);
		$bucketSelection = $bucket[ 'selection' ];

		return [
			'layer'          => [
				'bucket_selection' => $bucketSelection,
				'active_sections'  => $resolvedGroups[ 'active_sections' ],
				'healthy_sections' => $resolvedGroups[ 'healthy_sections' ],
				'header'           => $bucketSelection[ 'header' ],
			],
			'groups_indexed' => $resolvedGroups[ 'groups_indexed' ],
		];
	}

	/**
	 * @param list<BucketData> $buckets
	 * @return array<string,BucketData>
	 */
	private function indexBucketsByKey( array $buckets ) :array {
		$indexed = [];
		foreach ( $buckets as $bucket ) {
			$indexed[ $bucket[ 'key' ] ] = $bucket;
		}
		return $indexed;
	}

	/**
	 * @phpstan-param BucketSource $bucketSource
	 * @phpstan-param AssessmentRowsByZone $assessmentRowsByZone
	 * @return ResolvedGroups
	 */
	private function buildGroupsForBucket(
		string $bucketKey,
		string $bucketLabel,
		array $bucketSource,
		array $assessmentRowsByZone
	) :array {
		$activeSeeds = $this->seedCollector()->collect( $bucketKey, $bucketSource );
		$resolvedSeeds = \array_merge(
			$activeSeeds,
			$this->passiveSeedSupplementer()->supplement(
				$bucketKey,
				$bucketSource,
				$assessmentRowsByZone,
				\array_fill_keys( \array_column( $activeSeeds, 'key' ), true )
			)
		);

		return $this->contractBuilder()->buildResolvedGroups( $bucketLabel, $resolvedSeeds );
	}

	protected function buildGroupScanSource() :ActionsQueueGroupScanSource {
		return new ActionsQueueGroupScanSource(
			new ActionsQueueScanAssetCardsBuilder(
				$this->assetMetadataResolver(),
				$this->queueScanResultsOptions()
			),
			new ScansVulnerabilitiesBuilder(),
			$this->queueScanResultsOptions()
		);
	}

	protected function buildGroupMaintenanceSource() :ActionsQueueGroupMaintenanceSource {
		return new ActionsQueueGroupMaintenanceSource(
			new MaintenanceQueueItemDisplayNormalizer()
		);
	}

	protected function buildBucketsBuilder() :ActionsQueueBucketsBuilder {
		return new ActionsQueueBucketsBuilder( $this->buildRailTabAvailability() );
	}

	protected function buildRailTabAvailability() :ScansResultsRailTabAvailability {
		return new ScansResultsRailTabAvailability();
	}

	private function groupDefinitions() :ActionsQueueGroupDefinitions {
		if ( $this->groupDefinitions === null ) {
			$this->groupDefinitions = new ActionsQueueGroupDefinitions( $this->queueScanResultsOptions() );
		}

		return $this->groupDefinitions;
	}

	private function presentation() :ActionsQueueDrillDownPresentationBuilder {
		if ( $this->presentation === null ) {
			$this->presentation = new ActionsQueueDrillDownPresentationBuilder();
		}

		return $this->presentation;
	}

	private function queueScanResultsOptions() :ScanResultsDisplayOptions {
		if ( $this->queueScanResultsOptions === null ) {
			$this->queueScanResultsOptions = new ScanResultsDisplayOptions();
		}

		return $this->queueScanResultsOptions;
	}

	private function assetMetadataResolver() :ActionsQueueAssetMetadataResolver {
		if ( $this->assetMetadataResolver === null ) {
			$this->assetMetadataResolver = new ActionsQueueAssetMetadataResolver();
		}

		return $this->assetMetadataResolver;
	}

	private function maintenanceSeedBuilder() :ActionsQueueMaintenanceGroupSeedBuilder {
		if ( $this->maintenanceSeedBuilder === null ) {
			$this->maintenanceSeedBuilder = new ActionsQueueMaintenanceGroupSeedBuilder(
				$this->groupDefinitions(),
				new ActionsQueueCompactSummaryRowBuilder()
			);
		}

		return $this->maintenanceSeedBuilder;
	}

	private function seedCollector() :ActionsQueueGroupSeedCollector {
		if ( $this->seedCollector === null ) {
			$this->seedCollector = new ActionsQueueGroupSeedCollector(
				$this->groupDefinitions(),
				$this->queueScanResultsOptions(),
				$this->maintenanceSeedBuilder(),
				$this->groupScanSource(),
				$this->groupMaintenanceSource()
			);
		}

		return $this->seedCollector;
	}

	private function passiveSeedSupplementer() :ActionsQueuePassiveGroupSeedSupplementer {
		if ( $this->passiveSeedSupplementer === null ) {
			$this->passiveSeedSupplementer = new ActionsQueuePassiveGroupSeedSupplementer(
				$this->groupDefinitions(),
				$this->maintenanceSeedBuilder(),
				$this->groupScanSource(),
				$this->groupMaintenanceSource()
			);
		}

		return $this->passiveSeedSupplementer;
	}

	private function groupScanSource() :ActionsQueueGroupScanSource {
		if ( $this->groupScanSource === null ) {
			$this->groupScanSource = $this->buildGroupScanSource();
		}

		return $this->groupScanSource;
	}

	private function groupMaintenanceSource() :ActionsQueueGroupMaintenanceSource {
		if ( $this->groupMaintenanceSource === null ) {
			$this->groupMaintenanceSource = $this->buildGroupMaintenanceSource();
		}

		return $this->groupMaintenanceSource;
	}

	private function contractBuilder() :ActionsQueueGroupContractBuilder {
		if ( $this->contractBuilder === null ) {
			$this->contractBuilder = new ActionsQueueGroupContractBuilder(
				$this->groupDefinitions(),
				$this->presentation(),
				$this->assetMetadataResolver(),
				$this->queueScanResultsOptions()
			);
		}

		return $this->contractBuilder;
	}

	private function bucketsBuilder() :ActionsQueueBucketsBuilder {
		if ( $this->bucketsBuilder === null ) {
			$this->bucketsBuilder = $this->buildBucketsBuilder();
		}

		return $this->bucketsBuilder;
	}
}
