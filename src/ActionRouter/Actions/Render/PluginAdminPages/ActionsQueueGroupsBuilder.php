<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\LoadFileScanResultsTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionItem from BuildAttentionItems
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type AssessmentRowsByZone from ActionsQueueLandingAssessmentBuilder
 * @phpstan-import-type BucketData from ActionsQueueBucketsBuilder
 * @phpstan-import-type BucketSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type GroupSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type DrillLayerHeaderInput from PageDrillDownLandingBase
 * @phpstan-import-type GroupDefinition from ActionsQueueGroupDefinitions
 * @phpstan-import-type QueueAssetPane from ScansResultsViewBuilder
 * @phpstan-import-type VulnerabilityAction from ScansVulnerabilitiesBuilder
 * @phpstan-import-type VulnerabilitiesPayload from ScansVulnerabilitiesBuilder
 * @phpstan-import-type MaintenanceExpansionRow from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-import-type MaintenanceUiAction from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-type CompactMaintenanceRow array{
 *   title:string,
 *   action:array{},
 *   is_ignored:bool,
 *   ignored_label:string,
 *   secondary_actions:list<MaintenanceUiAction>
 * }
 * @phpstan-type SummaryRow array{
 *   icon_class:string,
 *   title:string,
 *   summary:string,
 *   badge_label:string,
 *   is_ignored:bool,
 *   actions:list<MaintenanceUiAction>
 * }
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
 *   maintenance_rows:list<CompactMaintenanceRow>,
 *   summary_row:array{}|SummaryRow,
 *   header:DrillLayerHeaderInput,
 *   selection_json:string,
 *   selection:GroupSelection
 * }
 * @phpstan-type GroupSectionData array{
 *   heading_label:string,
 *   groups:list<GroupData>
 * }
 * @phpstan-type GroupsLayerData array{
 *   bucket_selection:BucketSelection,
 *   healthy_heading_label:string,
 *   active_sections:list<GroupSectionData>,
 *   healthy_sections:list<GroupSectionData>,
 *   header:DrillLayerHeaderInput
 * }
 * @phpstan-type BucketSource array{
 *   attention_items:list<AttentionItem>,
 *   item_count:int
 * }
 * @phpstan-type GroupSeed array{
 *   key:string,
 *   is_healthy:bool,
 *   definition_key:string,
 *   heading_label:string,
 *   label:string,
 *   item_count:int,
 *   status:string,
 *   narrative:string,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   card_type_override?:'expandable'|'linked'|'category',
 *   icon_class_override?:string,
 *   links:list<GroupLink>,
 *   management_link:array{}|GroupManagementLink,
 *   is_interactive_override?:bool,
 *   detail_table:array<string,mixed>,
 *   render_action_data_override?:array<string,mixed>,
 *   attention_items:list<AttentionItem>,
 *   maintenance_rows:list<CompactMaintenanceRow>,
 *   summary_row:array{}|SummaryRow
 * }
 * @phpstan-type ComputedGroups array{
 *   layer:GroupsLayerData,
 *   groups_indexed:array<string,GroupData>
 * }
 * @phpstan-type ResolvedGroups array{
 *   groups_indexed:array<string,GroupData>,
 *   active_sections:list<GroupSectionData>,
 *   healthy_sections:list<GroupSectionData>
 * }
 */
class ActionsQueueGroupsBuilder {

	private const SECTION_ORDER = [
		'vulnerabilities' => 0,
		'wordpress'       => 1,
		'plugins'         => 2,
		'themes'          => 3,
		'malware'         => 4,
		'file_locker'     => 5,
		'maintenance'     => 6,
	];

	private ?ActionsQueueGroupDefinitions $groupDefinitions = null;
	private ?ActionsQueueDrillDownPresentationBuilder $presentation = null;
	private ?array $pluginsPane = null;
	private ?array $ignoredPluginsPane = null;
	private ?array $themesPane = null;
	private ?array $ignoredThemesPane = null;
	private ?array $vulnerabilitiesPayload = null;
	private ?int $ignoredWordpressCount = null;

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
		if ( isset( $computed[ 'groups_indexed' ][ $groupKey ] ) ) {
			return $computed[ 'groups_indexed' ][ $groupKey ];
		}

		return $this->buildEmptyGroup( $groupKey, $computed[ 'layer' ][ 'bucket_selection' ][ 'label' ] );
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
				?? $this->buildEmptyGroup( $groupKey, $computed[ 'layer' ][ 'bucket_selection' ][ 'label' ] ),
		];
	}

	/**
	 * @param AttentionQuery $attentionQuery
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return ComputedGroups
	 */
	private function compute( string $bucketKey, array $attentionQuery, array $assessmentRowsByZone ) :array {
		$bucketsBuilder = new ActionsQueueBucketsBuilder();
		$buckets = $this->indexBucketsByKey( $bucketsBuilder->build( $attentionQuery, $assessmentRowsByZone ) );
		$bucketSources = $bucketsBuilder->classify( $attentionQuery );
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
				'bucket_selection'      => $bucketSelection,
				'healthy_heading_label' => $this->healthySectionHeadingLabel(),
				'active_sections'       => $resolvedGroups[ 'active_sections' ],
				'healthy_sections'      => $resolvedGroups[ 'healthy_sections' ],
				'header'                => $bucketSelection[ 'header' ],
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
	 * @param BucketSource $bucketSource
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @return ResolvedGroups
	 */
	private function buildGroupsForBucket(
		string $bucketKey,
		string $bucketLabel,
		array $bucketSource,
		array $assessmentRowsByZone
	) :array {
		$seeds = [];
		$vulnerabilitiesPayload = null;
		$maintenanceItemsByKey = null;
		$pluginsExpanded = false;
		$themesExpanded = false;
		$vulnerableExpanded = false;
		$abandonedExpanded = false;

		foreach ( $bucketSource[ 'attention_items' ] as $item ) {
			$definitionKey = $this->groupDefinitions()->groupKeyForSummaryKey( $item[ 'key' ] );

			switch ( $definitionKey ) {
				case 'plugins':
					if ( !$pluginsExpanded ) {
						$pluginsExpanded = true;
						$seeds = \array_merge( $seeds, $this->buildPluginThemeSeeds( 'plugins', $item ) );
					}
					continue 2;

				case 'themes':
					if ( !$themesExpanded ) {
						$themesExpanded = true;
						$seeds = \array_merge( $seeds, $this->buildPluginThemeSeeds( 'themes', $item ) );
					}
					continue 2;

				case 'vulnerabilities':
					if ( $vulnerabilitiesPayload === null ) {
						$vulnerabilitiesPayload = $this->vulnerabilitiesPayload();
					}
					if ( $item[ 'key' ] === 'vulnerable_assets' && !$vulnerableExpanded ) {
						$vulnerableExpanded = true;
						$seeds = \array_merge(
							$seeds,
							$this->buildVulnerabilitySeeds( $vulnerabilitiesPayload[ 'sections' ][ 'vulnerable' ] ?? null, $item )
						);
					}
					elseif ( $item[ 'key' ] === 'abandoned' && !$abandonedExpanded ) {
						$abandonedExpanded = true;
						$seeds = \array_merge(
							$seeds,
							$this->buildVulnerabilitySeeds( $vulnerabilitiesPayload[ 'sections' ][ 'abandoned' ] ?? null, $item )
						);
					}
					continue 2;

				case 'maintenance':
					if ( $maintenanceItemsByKey === null ) {
						$maintenanceItemsByKey = $this->indexMaintenanceItemsByKey(
							$this->normalizeMaintenanceQueueItems( \array_values( \array_filter(
								$bucketSource[ 'attention_items' ],
								static fn( array $maintenanceItem ) :bool => ( $maintenanceItem[ 'zone' ] ?? '' ) === 'maintenance'
							) ) )
						);
					}
					if ( isset( $maintenanceItemsByKey[ $item[ 'key' ] ] ) ) {
						$seeds[] = $this->buildMaintenanceSeed( $maintenanceItemsByKey[ $item[ 'key' ] ] );
					}
					continue 2;
			}

			$seedKey = $definitionKey;
			if ( !isset( $seeds[ $seedKey ] ) ) {
				$definition = $this->getGroupDefinition( $definitionKey );
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

		$resolvedSeeds = \array_values( \array_filter( $seeds, static fn( array $seed ) :bool => $seed[ 'label' ] !== '' ) );

		$resolvedSeeds = \array_merge(
			$resolvedSeeds,
			$this->buildHealthyBucketSeeds(
				$bucketKey,
				$bucketSource,
				$assessmentRowsByZone,
				\array_fill_keys( \array_column( $resolvedSeeds, 'key' ), true )
			)
		);

		\usort( $resolvedSeeds, function ( array $left, array $right ) :int {
			$sectionCmp = $this->sectionOrderForSeed( $left ) <=> $this->sectionOrderForSeed( $right );
			if ( $sectionCmp !== 0 ) {
				return $sectionCmp;
			}

			$orderCmp = $this->definitionOrderForSeed( $left ) <=> $this->definitionOrderForSeed( $right );
			if ( $orderCmp !== 0 ) {
				return $orderCmp;
			}

			$headingCmp = \strcmp( $left[ 'heading_label' ], $right[ 'heading_label' ] );
			if ( $headingCmp !== 0 ) {
				return $headingCmp;
			}

			$statusCmp = StatusPriority::rank( $right[ 'status' ] ) <=> StatusPriority::rank( $left[ 'status' ] );
			if ( $statusCmp !== 0 ) {
				return $statusCmp;
			}

			$countCmp = $right[ 'item_count' ] <=> $left[ 'item_count' ];
			if ( $countCmp !== 0 ) {
				return $countCmp;
			}

			return \strcmp( $left[ 'label' ], $right[ 'label' ] );
		} );

		$indexed = [];
		$activeEntries = [];
		$healthyEntries = [];
		foreach ( $resolvedSeeds as $seed ) {
			$group = $this->resolveSeed( $bucketLabel, $seed );
			$indexed[ $group[ 'key' ] ] = $group;
			if ( $seed[ 'is_healthy' ] ) {
				$healthyEntries[] = [
					'heading_label' => $seed[ 'heading_label' ],
					'group'         => $group,
				];
			}
			else {
				$activeEntries[] = [
					'heading_label' => $seed[ 'heading_label' ],
					'group'         => $group,
				];
			}
		}

		return [
			'groups_indexed'  => $indexed,
			'active_sections' => $this->buildSectionsFromEntries( $activeEntries ),
			'healthy_sections' => $this->buildSectionsFromEntries( $healthyEntries ),
		];
	}

	/**
	 * @param GroupSeed $seed
	 * @return GroupData
	 */
	private function resolveSeed( string $bucketLabel, array $seed ) :array {
		$definition = $this->getGroupDefinition( $seed[ 'definition_key' ] );
		$iconClass = $seed[ 'icon_class_override' ] ?? $definition[ 'icon_class' ];
		$narrative = $seed[ 'narrative' ] !== ''
			? $seed[ 'narrative' ]
			: $this->buildNarrative( $seed[ 'definition_key' ], $seed[ 'attention_items' ], $seed[ 'item_count' ] );
		$isInteractive = $seed[ 'is_interactive_override' ]
			?? $this->determineInteractivity( $seed );
		$selection = $this->presentation()->buildGroupSelection(
			$bucketLabel,
			$seed[ 'key' ],
			$seed[ 'label' ],
			$seed[ 'status' ],
			$iconClass,
			$seed[ 'item_count' ],
			$seed[ 'detail_shell' ],
			$narrative
		);

		return [
			'key'                 => $seed[ 'key' ],
			'label'               => $seed[ 'label' ],
			'item_count'          => $seed[ 'item_count' ],
			'status'              => $seed[ 'status' ],
			'icon_class'          => $iconClass,
			'detail_shell'        => $seed[ 'detail_shell' ],
			'card_type'           => $seed[ 'card_type_override' ] ?? $definition[ 'card_type' ],
			'narrative'           => $narrative,
			'drill_hint'          => $this->buildDrillHint(
				$definition,
				$seed[ 'item_count' ],
				$seed[ 'detail_shell' ],
				$seed[ 'status' ]
			),
			'links'               => $seed[ 'links' ],
			'management_link'     => $seed[ 'management_link' ],
			'is_interactive'      => $isInteractive,
			'detail_table'        => $seed[ 'detail_table' ],
			'render_action_class' => $definition[ 'render_action_class' ],
			'render_action_data'  => $seed[ 'render_action_data_override' ]
				?? $definition[ 'render_action_data' ],
			'maintenance_rows'    => $seed[ 'maintenance_rows' ],
			'summary_row'         => $seed[ 'summary_row' ],
			'header'              => $selection[ 'header' ],
			'selection_json'      => $selection[ 'selection_json' ],
			'selection'           => $selection,
		];
	}

	/**
	 * @return GroupData
	 */
	private function buildEmptyGroup( string $groupKey, string $bucketLabel ) :array {
		$definitionKey = $this->definitionKeyForGroupKey( $groupKey );
		$definition = $this->getGroupDefinition( $definitionKey );
		$narrative = __( 'No matching items remain in this group.', 'wp-simple-firewall' );
		$selection = $this->presentation()->buildGroupSelection(
			$bucketLabel,
			$groupKey,
			$definition[ 'label' ],
			'good',
			$definition[ 'icon_class' ],
			0,
			$definition[ 'detail_shell' ],
			$narrative
		);

		return [
			'key'                 => $groupKey,
			'label'               => $definition[ 'label' ],
			'item_count'          => 0,
			'status'              => 'good',
			'icon_class'          => $definition[ 'icon_class' ],
			'detail_shell'        => $definition[ 'detail_shell' ],
			'card_type'           => $definition[ 'card_type' ],
			'narrative'           => $narrative,
			'drill_hint'          => '',
			'links'               => [],
			'management_link'     => [],
			'is_interactive'      => false,
			'detail_table'        => [],
			'render_action_class' => $definition[ 'render_action_class' ],
			'render_action_data'  => $definition[ 'render_action_data' ],
			'maintenance_rows'    => [],
			'summary_row'         => [],
			'header'              => $selection[ 'header' ],
			'selection_json'      => $selection[ 'selection_json' ],
			'selection'           => $selection,
		];
	}

	/**
	 * @param AttentionItem $item
	 * @return list<GroupSeed>
	 */
	private function buildPluginThemeSeeds( string $definitionKey, array $item ) :array {
		$definition = $this->getGroupDefinition( $definitionKey );
		$cards = $definitionKey === 'plugins'
			? $this->pluginsPane()[ 'cards' ]
			: $this->themesPane()[ 'cards' ];
		$seeds = [];

		foreach ( $cards as $card ) {
			$fileCount = \max( 0, (int)( $card[ 'count_badge' ] ?? 0 ) );
			if ( $fileCount < 1 ) {
				continue;
			}

			$seeds[] = [
				'key'              => $definitionKey.':'.$card[ 'key' ],
				'is_healthy'       => false,
				'definition_key'   => $definitionKey,
				'heading_label'    => $definition[ 'label' ],
				'label'            => $card[ 'title' ],
				'item_count'       => $fileCount,
				'status'           => StatusPriority::normalize( $item[ 'severity' ], 'warning' ),
				'narrative'        => $card[ 'stat_text' ],
				'detail_shell'     => 'direct_table',
				'links'            => [],
				'management_link'  => [],
				'detail_table'     => $card[ 'table' ],
				'attention_items'  => [ $item ],
				'maintenance_rows' => [],
				'summary_row'      => [],
			];
		}

		return $seeds;
	}

	/**
	 * @param AttentionItem $item
	 * @return list<GroupSeed>
	 */
	private function buildVulnerabilitySeeds( ?array $section, array $item ) :array {
		if ( !\is_array( $section ) ) {
			return [];
		}

		$seeds = [];
		foreach ( $section[ 'items' ] as $vulnerabilityItem ) {
			$seeds[] = [
				'key'              => 'vulnerabilities:'.$vulnerabilityItem[ 'key' ],
				'is_healthy'       => false,
				'definition_key'   => 'vulnerabilities',
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
	 * @return GroupSeed
	 */
	private function buildMaintenanceSeed( array $maintenanceItem, bool $isHealthy = false ) :array {
		$maintenanceRows = $this->projectMaintenanceRows( $maintenanceItem );

		return [
			'key'                 => $maintenanceItem[ 'key' ],
			'is_healthy'          => $isHealthy,
			'definition_key'      => 'maintenance',
			'heading_label'       => '',
			'label'               => $maintenanceItem[ 'label' ],
			'item_count'          => $isHealthy
				? $this->maintenanceVisibleCount( $maintenanceItem )
				: (int)$maintenanceItem[ 'count' ],
			'status'              => StatusPriority::normalize( $maintenanceItem[ 'severity' ], 'warning' ),
			'narrative'           => $maintenanceItem[ 'description' ],
			'detail_shell'        => 'maintenance',
			'icon_class_override' => $maintenanceItem[ 'icon_class' ] ?? null,
			'links'               => [],
			'management_link'     => $this->buildManagementLink( $maintenanceItem ),
			'detail_table'        => [],
			'attention_items'     => [],
			'maintenance_rows'    => $maintenanceRows,
			'summary_row'         => empty( $maintenanceRows )
				? $this->buildMaintenanceSummaryRow( $maintenanceItem )
				: [],
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
	 * @return array{}|GroupManagementLink
	 */
	private function buildManagementLink( array $maintenanceItem ) :array {
		$cta = \is_array( $maintenanceItem[ 'cta' ] ?? null ) ? $maintenanceItem[ 'cta' ] : [];
		$label = \trim( (string)( $cta[ 'label' ] ?? '' ) );
		$href = \trim( (string)( $cta[ 'href' ] ?? '' ) );
		$target = \trim( (string)( $cta[ 'target' ] ?? '' ) );
		if ( $label === '' || $href === '' ) {
			return [];
		}

		return [
			'label'      => $label,
			'href'       => $href,
			'target'     => $target,
			'rel'        => $target === '_blank' ? 'noopener noreferrer' : '',
			'icon_class' => $target === '_blank' ? 'bi-box-arrow-up-right' : 'bi-arrow-right',
		];
	}

	/**
	 * @param BucketSource $bucketSource
	 * @param AssessmentRowsByZone $assessmentRowsByZone
	 * @param array<string,true> $existingGroupKeys
	 * @return list<GroupSeed>
	 */
	private function buildHealthyBucketSeeds(
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

			$seeds[] = $this->buildMaintenanceSeed( $maintenanceItem, true );
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

			$definitionKey = $this->groupDefinitions()->groupKeyForSummaryKey( $row[ 'key' ] );
			if ( $definitionKey === 'maintenance' ) {
				continue;
			}

			$rowsByDefinitionKey[ $definitionKey ][] = $row;
		}

		$seeds = [];
		foreach ( $rowsByDefinitionKey as $definitionKey => $rows ) {
			$definition = $this->getGroupDefinition( $definitionKey );
			$interaction = $this->buildHealthyScanInteraction( $definitionKey );
			$seed = [
				'key'              => $definitionKey,
				'is_healthy'       => true,
				'definition_key'   => $definitionKey,
				'heading_label'    => $definition[ 'label' ],
				'label'            => $definition[ 'label' ],
				'item_count'       => $interaction[ 'item_count' ] > 0
					? $interaction[ 'item_count' ]
					: \count( $rows ),
				'status'           => 'good',
				'narrative'        => $this->combineHealthyAssessmentNarratives( $rows ),
				'detail_shell'     => $definition[ 'detail_shell' ],
				'links'            => [],
				'management_link'  => [],
				'is_interactive_override' => $interaction[ 'is_interactive' ],
				'detail_table'     => [],
				'render_action_data_override' => $interaction[ 'render_action_data' ],
				'attention_items'  => [],
				'maintenance_rows' => [],
				'summary_row'      => [],
			];
			$seeds[] = $seed;
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
	 * @param list<AttentionItem> $attentionItems
	 */
	private function buildNarrative( string $definitionKey, array $attentionItems, int $itemCount ) :string {
		switch ( $definitionKey ) {
			case 'vulnerabilities':
				$vulnerableCount = $this->countAttentionItemsByKey( $attentionItems, 'vulnerable_assets' );
				$abandonedCount = $this->countAttentionItemsByKey( $attentionItems, 'abandoned' );
				if ( $vulnerableCount > 0 && $abandonedCount > 0 ) {
					return \sprintf(
						__( '%1$s vulnerable assets and %2$s abandoned assets need review.', 'wp-simple-firewall' ),
						$vulnerableCount,
						$abandonedCount
					);
				}
				if ( $vulnerableCount > 0 ) {
					return \sprintf(
						_n( '%s vulnerable asset needs review.', '%s vulnerable assets need review.', $vulnerableCount, 'wp-simple-firewall' ),
						$vulnerableCount
					);
				}
				return \sprintf(
					_n( '%s abandoned asset needs review.', '%s abandoned assets need review.', $abandonedCount, 'wp-simple-firewall' ),
					$abandonedCount
				);

			case 'wordpress':
				return \sprintf(
					_n( '%s WordPress core file needs review.', '%s WordPress core files need review.', $itemCount, 'wp-simple-firewall' ),
					$itemCount
				);

			case 'plugins':
				return \sprintf(
					_n( '%s file needs review.', '%s files need review.', $itemCount, 'wp-simple-firewall' ),
					$itemCount
				);

			case 'themes':
				return \sprintf(
					_n( '%s file needs review.', '%s files need review.', $itemCount, 'wp-simple-firewall' ),
					$itemCount
				);

			case 'malware':
				return \sprintf(
					_n( '%s suspected malware result needs review.', '%s suspected malware results need review.', $itemCount, 'wp-simple-firewall' ),
					$itemCount
				);

			case 'file_locker':
				return \sprintf(
					_n( '%s locked file change needs review.', '%s locked file changes need review.', $itemCount, 'wp-simple-firewall' ),
					$itemCount
				);

			case 'maintenance':
				return \sprintf(
					_n( '%s maintenance item needs review.', '%s maintenance items need review.', $itemCount, 'wp-simple-firewall' ),
					$itemCount
				);

			default:
				return \sprintf(
					_n( '%s item needs review.', '%s items need review.', $itemCount, 'wp-simple-firewall' ),
					$itemCount
				);
		}
	}

	private function healthySectionHeadingLabel() :string {
		return __( 'No action required', 'wp-simple-firewall' );
	}

	private function buildDrillHint( array $definition, int $itemCount, string $detailShell, string $status ) :string {
		if ( $itemCount < 1 || $detailShell === 'maintenance' || $status === 'good' ) {
			return '';
		}
		if ( $definition[ 'drill_hint_single' ] === '' || $definition[ 'drill_hint_plural' ] === '' ) {
			return '';
		}

		$pattern = _n(
			$definition[ 'drill_hint_single' ],
			$definition[ 'drill_hint_plural' ],
			$itemCount,
			'wp-simple-firewall'
		);

		return \sprintf( $pattern, number_format_i18n( $itemCount ) );
	}

	/**
	 * @param list<AttentionItem> $items
	 */
	private function countAttentionItemsByKey( array $items, string $itemKey ) :int {
		$count = 0;
		foreach ( $items as $item ) {
			if ( $item[ 'key' ] === $itemKey ) {
				$count += $item[ 'count' ];
			}
		}
		return $count;
	}

	/**
	 * @param GroupSeed $seed
	 */
	private function definitionOrderForSeed( array $seed ) :int {
		return self::SECTION_ORDER[ $seed[ 'definition_key' ] ] ?? 999;
	}

	/**
	 * @param GroupSeed $seed
	 */
	private function sectionOrderForSeed( array $seed ) :int {
		return $seed[ 'is_healthy' ] ? 1 : 0;
	}

	/**
	 * @param list<array{heading_label:string,group:GroupData}> $entries
	 * @return list<GroupSectionData>
	 */
	private function buildSectionsFromEntries( array $entries ) :array {
		$sections = [];
		foreach ( $entries as $entry ) {
			$headingLabel = $entry[ 'heading_label' ];
			$shouldStartNew = empty( $sections )
				|| $sections[ \array_key_last( $sections ) ][ 'heading_label' ] !== $headingLabel;
			if ( $shouldStartNew ) {
				$sections[] = [
					'heading_label' => $headingLabel,
					'groups'        => [],
				];
			}
			$sections[ \array_key_last( $sections ) ][ 'groups' ][] = $entry[ 'group' ];
		}

		foreach ( $sections as &$section ) {
			if ( \count( $section[ 'groups' ] ) === 1
				&& $section[ 'heading_label' ] === $section[ 'groups' ][ 0 ][ 'label' ] ) {
				$section[ 'heading_label' ] = '';
			}
		}
		unset( $section );

		return $sections;
	}

	/**
	 * @param list<MaintenanceQueueItem> $maintenanceItems
	 * @return array<string,MaintenanceQueueItem>
	 */
	private function indexMaintenanceItemsByKey( array $maintenanceItems ) :array {
		$indexed = [];
		foreach ( $maintenanceItems as $item ) {
			$indexed[ $item[ 'key' ] ] = $item;
		}
		return $indexed;
	}

	private function definitionKeyForGroupKey( string $groupKey ) :string {
		if ( \strpos( $groupKey, ':' ) !== false ) {
			$maybeDefinitionKey = \strstr( $groupKey, ':', true );
			if ( \is_string( $maybeDefinitionKey ) && isset( $this->groupDefinitions()->all()[ $maybeDefinitionKey ] ) ) {
				return $maybeDefinitionKey;
			}
		}

		return isset( $this->groupDefinitions()->all()[ $groupKey ] )
			? $groupKey
			: 'maintenance';
	}

	/**
	 * @return GroupDefinition
	 */
	private function getGroupDefinition( string $groupKey ) :array {
		return $this->groupDefinitions()->all()[ $this->definitionKeyForGroupKey( $groupKey ) ];
	}

	/**
	 * @return QueueAssetPane
	 */
	private function pluginsPane() :array {
		if ( $this->pluginsPane === null ) {
			$this->pluginsPane = $this->buildActionsQueuePluginsPane( $this->queueScanResultsOptions()->activeOnly() );
		}

		return $this->pluginsPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function ignoredPluginsPane() :array {
		if ( $this->ignoredPluginsPane === null ) {
			$this->ignoredPluginsPane = $this->buildActionsQueuePluginsPane( $this->queueScanResultsOptions()->ignoredOnly() );
		}

		return $this->ignoredPluginsPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function themesPane() :array {
		if ( $this->themesPane === null ) {
			$this->themesPane = $this->buildActionsQueueThemesPane( $this->queueScanResultsOptions()->activeOnly() );
		}

		return $this->themesPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function ignoredThemesPane() :array {
		if ( $this->ignoredThemesPane === null ) {
			$this->ignoredThemesPane = $this->buildActionsQueueThemesPane( $this->queueScanResultsOptions()->ignoredOnly() );
		}

		return $this->ignoredThemesPane;
	}

	/**
	 * @return VulnerabilitiesPayload
	 */
	private function vulnerabilitiesPayload() :array {
		if ( $this->vulnerabilitiesPayload === null ) {
			$this->vulnerabilitiesPayload = $this->buildVulnerabilitiesPayload();
		}

		return $this->vulnerabilitiesPayload;
	}

	protected function buildActionsQueuePluginsPane( array $resultsDisplayOptions = [] ) :array {
		return ( new ScansResultsViewBuilder() )->buildActionsQueuePluginsPane( $resultsDisplayOptions );
	}

	protected function buildActionsQueueThemesPane( array $resultsDisplayOptions = [] ) :array {
		return ( new ScansResultsViewBuilder() )->buildActionsQueueThemesPane( $resultsDisplayOptions );
	}

	protected function buildVulnerabilitiesPayload() :array {
		return ( new ScansVulnerabilitiesBuilder() )->build();
	}

	/**
	 * @param list<AttentionItem> $items
	 * @return list<MaintenanceQueueItem>
	 */
	protected function normalizeMaintenanceQueueItems( array $items ) :array {
		return ( new MaintenanceQueueItemDisplayNormalizer() )->normalizeAll( $items );
	}

	/**
	 * @param list<AttentionItem> $items
	 * @return list<MaintenanceQueueItem>
	 */
	protected function normalizeBucketMaintenanceQueueItems( array $items, string $bucketKey ) :array {
		return ( new MaintenanceQueueItemDisplayNormalizer() )->normalizeForBucket( $items, $bucketKey );
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return list<MaintenanceExpansionRow>
	 */
	private function extractMaintenanceRows( array $maintenanceItem ) :array {
		$rows = $maintenanceItem[ 'expansion' ][ 'table' ][ 'rows' ] ?? null;
		return \is_array( $rows ) ? $rows : [];
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return list<CompactMaintenanceRow>
	 */
	private function projectMaintenanceRows( array $maintenanceItem ) :array {
		return \array_values( \array_map(
			static fn( array $row ) :array => [
				'title'             => (string)( $row[ 'title' ] ?? '' ),
				'action'            => [],
				'is_ignored'        => (bool)( $row[ 'is_ignored' ] ?? false ),
				'ignored_label'     => (string)( $row[ 'ignored_label' ] ?? '' ),
				'secondary_actions' => \is_array( $row[ 'secondary_actions' ] ?? null )
					? $row[ 'secondary_actions' ]
					: [],
			],
			$this->extractMaintenanceRows( $maintenanceItem )
		) );
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 * @return array{}|SummaryRow
	 */
	private function buildMaintenanceSummaryRow( array $maintenanceItem ) :array {
		$toggleAction = \is_array( $maintenanceItem[ 'toggle_action' ] ?? null ) ? $maintenanceItem[ 'toggle_action' ] : [];
		if ( \trim( (string)( $maintenanceItem[ 'description' ] ?? '' ) ) === '' && empty( $toggleAction ) ) {
			return [];
		}

		$isIgnored = ( $toggleAction[ 'kind' ] ?? '' ) === 'unignore';
		return [
			'icon_class'  => (string)( $maintenanceItem[ 'icon_class' ] ?? '' ),
			'title'       => '',
			'summary'     => (string)( $maintenanceItem[ 'description' ] ?? '' ),
			'badge_label' => $isIgnored ? __( 'Currently ignored', 'wp-simple-firewall' ) : '',
			'is_ignored'  => $isIgnored,
			'actions'     => empty( $toggleAction ) ? [] : [ $toggleAction ],
		];
	}

	/**
	 * @phpstan-param MaintenanceQueueItem $maintenanceItem
	 */
	private function maintenanceVisibleCount( array $maintenanceItem ) :int {
		$activeCount = (int)( $maintenanceItem[ 'count' ] ?? 0 );
		if ( $activeCount > 0 ) {
			return $activeCount;
		}

		$rowCount = \count( $this->projectMaintenanceRows( $maintenanceItem ) );
		if ( $rowCount > 0 ) {
			return $rowCount;
		}

		return empty( $this->buildMaintenanceSummaryRow( $maintenanceItem ) ) ? 0 : 1;
	}

	/**
	 * @param GroupSeed $seed
	 */
	private function determineInteractivity( array $seed ) :bool {
		return ( $seed[ 'detail_shell' ] ?? '' ) !== 'maintenance'
			&& ( $seed[ 'card_type_override' ] ?? '' ) !== 'linked'
			&& (
				!empty( $seed[ 'detail_table' ] )
				|| !empty( $seed[ 'render_action_data_override' ] )
				|| ( $seed[ 'item_count' ] ?? 0 ) > 0
			);
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
				$ignoredCount = $this->getIgnoredWordpressCount();
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
				? $this->queueScanResultsOptions()->buildActionData(
					$this->queueScanResultsOptions()->ignoredOnly()
				)
				: [],
		];
	}

	protected function getIgnoredWordpressCount() :int {
		if ( $this->ignoredWordpressCount === null ) {
			$loader = new LoadFileScanResultsTableData();
			$loader->custom_record_retriever_wheres = [
				\sprintf( "%s.`meta_key`='is_in_core'", RetrieveBase::ABBR_RESULTITEMMETA ),
				\sprintf( "%s.`meta_value`=1", RetrieveBase::ABBR_RESULTITEMMETA ),
			];
			$loader->results_display_options = $this->queueScanResultsOptions()->ignoredOnly();
			$this->ignoredWordpressCount = $loader->countAll();
		}

		return $this->ignoredWordpressCount;
	}

	private function countQueueAssetPaneResults( array $pane ) :int {
		return (int)\array_sum( \array_map(
			static fn( array $card ) :int => (int)( $card[ 'count_badge' ] ?? 0 ),
			$pane[ 'cards' ] ?? []
		) );
	}

	private function groupDefinitions() :ActionsQueueGroupDefinitions {
		if ( $this->groupDefinitions === null ) {
			$this->groupDefinitions = new ActionsQueueGroupDefinitions();
		}

		return $this->groupDefinitions;
	}

	private function presentation() :ActionsQueueDrillDownPresentationBuilder {
		if ( $this->presentation === null ) {
			$this->presentation = new ActionsQueueDrillDownPresentationBuilder();
		}

		return $this->presentation;
	}

	private function queueScanResultsOptions() :ActionsQueueScanResultsOptions {
		return new ActionsQueueScanResultsOptions();
	}
}
