<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionItem from BuildAttentionItems
 * @phpstan-import-type AttentionQuery from BuildAttentionItems
 * @phpstan-import-type BucketData from ActionsQueueBucketsBuilder
 * @phpstan-import-type BucketSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type GroupSelection from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type LayerContext from ActionsQueueDrillDownPresentationBuilder
 * @phpstan-import-type GroupDefinition from ActionsQueueGroupDefinitions
 * @phpstan-import-type QueueAssetPane from ScansResultsViewBuilder
 * @phpstan-import-type VulnerabilityAction from ScansVulnerabilitiesBuilder
 * @phpstan-import-type VulnerabilitiesPayload from ScansVulnerabilitiesBuilder
 * @phpstan-import-type MaintenanceQueueItem from MaintenanceQueueItemDisplayNormalizer
 * @phpstan-type AssessmentRow array{
 *   key:string,
 *   label:string,
 *   description:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string
 * }
 * @phpstan-type AssessmentRowsByZone array{
 *   scans:list<AssessmentRow>,
 *   maintenance:list<AssessmentRow>
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
 *   heading_label:string,
 *   label:string,
 *   item_count:int,
 *   status:string,
 *   icon_class:string,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   card_type:'expandable'|'linked'|'category',
 *   narrative:string,
 *   next_move:string,
 *   drill_hint:string,
 *   links:list<GroupLink>,
 *   management_link:array{}|GroupManagementLink,
 *   detail_table:array<string,mixed>,
 *   render_action_class:class-string<BaseAction>,
 *   render_action_data:array<string,string>,
 *   maintenance_items:list<array{icon_class:string, title:string, summary:string}>,
 *   strip_text:string,
 *   strip_badge:string,
 *   context:LayerContext,
 *   context_json:string,
 *   selection_json:string,
 *   selection:GroupSelection
 * }
 * @phpstan-type GroupsLayerData array{
 *   bucket_selection:BucketSelection,
 *   bucket_selection_json:string,
 *   groups:list<GroupData>,
 *   context:LayerContext,
 *   strip_text:string,
 *   strip_badge:string,
 *   strip_badge_status:string
 * }
 * @phpstan-type BucketSource array{
 *   attention_items:list<AttentionItem>,
 *   item_count:int
 * }
 * @phpstan-type GroupSeed array{
 *   key:string,
 *   definition_key:string,
 *   heading_label:string,
 *   label:string,
 *   item_count:int,
 *   status:string,
 *   narrative:string,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   path_segments:list<string>,
 *   links:list<GroupLink>,
 *   management_link:array{}|GroupManagementLink,
 *   detail_table:array<string,mixed>,
 *   attention_items:list<AttentionItem>,
 *   maintenance_rows:list<AssessmentRow>
 * }
 * @phpstan-type ComputedGroups array{
 *   layer:GroupsLayerData,
 *   groups_indexed:array<string,GroupData>
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
	private ?array $themesPane = null;
	private ?array $vulnerabilitiesPayload = null;

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
		$groupsIndexed = $this->buildGroupsIndexedForBucket(
			$bucket[ 'label' ],
			$bucketSources[ $bucketKey ],
			$assessmentRowsByZone[ 'maintenance' ]
		);
		$bucketSelection = $bucket[ 'selection' ];

		return [
			'layer'          => [
				'bucket_selection'      => $bucketSelection,
				'bucket_selection_json' => $bucket[ 'selection_json' ],
				'groups'                => \array_values( $groupsIndexed ),
				'context'               => $bucketSelection[ 'context' ],
				'strip_text'            => $bucketSelection[ 'strip_text' ],
				'strip_badge'           => $bucketSelection[ 'strip_badge' ],
				'strip_badge_status'    => $bucketSelection[ 'status' ],
			],
			'groups_indexed' => $groupsIndexed,
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
	 * @param list<AssessmentRow> $maintenanceRows
	 * @return array<string,GroupData>
	 */
	private function buildGroupsIndexedForBucket( string $bucketLabel, array $bucketSource, array $maintenanceRows ) :array {
		$seeds = [];
		$maintenanceRowsByKey = $this->indexMaintenanceRowsByKey( $maintenanceRows );
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
						$seeds[] = $this->buildMaintenanceSeed(
							$maintenanceItemsByKey[ $item[ 'key' ] ],
							$maintenanceRowsByKey[ $item[ 'key' ] ] ?? null
						);
					}
					continue 2;
			}

			$seedKey = $definitionKey;
			if ( !isset( $seeds[ $seedKey ] ) ) {
				$definition = $this->getGroupDefinition( $definitionKey );
				$seeds[ $seedKey ] = [
					'key'              => $definitionKey,
					'definition_key'   => $definitionKey,
					'heading_label'    => $definition[ 'label' ],
					'label'            => $definition[ 'label' ],
					'item_count'       => 0,
					'status'           => 'good',
					'narrative'        => '',
					'detail_shell'     => $definition[ 'detail_shell' ],
					'path_segments'    => [ $definition[ 'label' ] ],
					'links'            => [],
					'management_link'  => [],
					'detail_table'     => [],
					'attention_items'  => [],
					'maintenance_rows' => [],
				];
			}

			$seeds[ $seedKey ][ 'item_count' ] += $item[ 'count' ];
			$seeds[ $seedKey ][ 'status' ] = StatusPriority::highest( [
				$seeds[ $seedKey ][ 'status' ],
				$item[ 'severity' ],
			], 'good' );
			$seeds[ $seedKey ][ 'attention_items' ][] = $item;
		}

		$resolved = \array_values( \array_map(
			fn( array $seed ) :array => $this->resolveSeed( $bucketLabel, $seed ),
			\array_values( \array_filter( $seeds, static fn( array $seed ) :bool => $seed[ 'label' ] !== '' ) )
		) );

		\usort( $resolved, function ( array $left, array $right ) :int {
			$orderCmp = $this->sectionOrderForGroup( $left ) <=> $this->sectionOrderForGroup( $right );
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
		foreach ( $resolved as $group ) {
			$indexed[ $group[ 'key' ] ] = $group;
		}

		return $indexed;
	}

	/**
	 * @param GroupSeed $seed
	 * @return GroupData
	 */
	private function resolveSeed( string $bucketLabel, array $seed ) :array {
		$definition = $this->getGroupDefinition( $seed[ 'definition_key' ] );
		$narrative = $seed[ 'narrative' ] !== ''
			? $seed[ 'narrative' ]
			: $this->buildNarrative( $seed[ 'definition_key' ], $seed[ 'attention_items' ], $seed[ 'item_count' ] );
		$nextMove = $this->buildNextMove( $seed[ 'definition_key' ] );
		$context = [
			'path'      => \array_merge(
				[ __( 'Triage buckets', 'wp-simple-firewall' ), $bucketLabel ],
				$seed[ 'path_segments' ]
			),
			'focus'     => $narrative,
			'next_step' => $nextMove,
		];
		$selection = $this->presentation()->buildGroupSelection(
			$seed[ 'key' ],
			$seed[ 'label' ],
			$seed[ 'status' ],
			$seed[ 'item_count' ],
			$seed[ 'detail_shell' ],
			$context
		);

		return [
			'key'                 => $seed[ 'key' ],
			'heading_label'       => $seed[ 'heading_label' ],
			'label'               => $seed[ 'label' ],
			'item_count'          => $seed[ 'item_count' ],
			'status'              => $seed[ 'status' ],
			'icon_class'          => $definition[ 'icon_class' ],
			'detail_shell'        => $seed[ 'detail_shell' ],
			'card_type'           => $definition[ 'card_type' ],
			'narrative'           => $narrative,
			'next_move'           => $nextMove,
			'drill_hint'          => $this->buildDrillHint( $definition, $seed[ 'item_count' ], $seed[ 'detail_shell' ] ),
			'links'               => $seed[ 'links' ],
			'management_link'     => $seed[ 'management_link' ],
			'detail_table'        => $seed[ 'detail_table' ],
			'render_action_class' => $definition[ 'render_action_class' ],
			'render_action_data'  => $definition[ 'render_action_data' ],
			'maintenance_items'   => $this->buildMaintenanceItems( $seed[ 'maintenance_rows' ] ),
			'strip_text'          => $selection[ 'strip_text' ],
			'strip_badge'         => $selection[ 'strip_badge' ],
			'context'             => $context,
			'context_json'        => $selection[ 'context_json' ],
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
		$nextMove = __( 'Go back to the grouped findings and pick another area to review.', 'wp-simple-firewall' );
		$context = [
			'path'      => [
				__( 'Triage buckets', 'wp-simple-firewall' ),
				$bucketLabel,
				$definition[ 'label' ],
			],
			'focus'     => $narrative,
			'next_step' => $nextMove,
		];
		$selection = $this->presentation()->buildGroupSelection(
			$groupKey,
			$definition[ 'label' ],
			'good',
			0,
			$definition[ 'detail_shell' ],
			$context
		);

		return [
			'key'                 => $groupKey,
			'heading_label'       => $definition[ 'label' ],
			'label'               => $definition[ 'label' ],
			'item_count'          => 0,
			'status'              => 'good',
			'icon_class'          => $definition[ 'icon_class' ],
			'detail_shell'        => $definition[ 'detail_shell' ],
			'card_type'           => $definition[ 'card_type' ],
			'narrative'           => $narrative,
			'next_move'           => $nextMove,
			'drill_hint'          => '',
			'links'               => [],
			'management_link'     => [],
			'detail_table'        => [],
			'render_action_class' => $definition[ 'render_action_class' ],
			'render_action_data'  => $definition[ 'render_action_data' ],
			'maintenance_items'   => [],
			'strip_text'          => $selection[ 'strip_text' ],
			'strip_badge'         => $selection[ 'strip_badge' ],
			'context'             => $context,
			'context_json'        => $selection[ 'context_json' ],
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
				'definition_key'   => $definitionKey,
				'heading_label'    => $definition[ 'label' ],
				'label'            => $card[ 'title' ],
				'item_count'       => $fileCount,
				'status'           => StatusPriority::normalize( $item[ 'severity' ], 'warning' ),
				'narrative'        => $card[ 'stat_text' ],
				'detail_shell'     => 'direct_table',
				'path_segments'    => [ $definition[ 'label' ], $card[ 'title' ] ],
				'links'            => [],
				'management_link'  => [],
				'detail_table'     => $card[ 'table' ],
				'attention_items'  => [ $item ],
				'maintenance_rows' => [],
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
				'definition_key'   => 'vulnerabilities',
				'heading_label'    => $section[ 'label' ],
				'label'            => $vulnerabilityItem[ 'label' ],
				'item_count'       => $vulnerabilityItem[ 'count' ],
				'status'           => StatusPriority::normalize( $vulnerabilityItem[ 'severity' ], 'warning' ),
				'narrative'        => $vulnerabilityItem[ 'description' ],
				'detail_shell'     => 'direct_table',
				'path_segments'    => [ $section[ 'label' ], $vulnerabilityItem[ 'label' ] ],
				'links'            => $this->buildGroupLinksFromVulnerabilityActions( $vulnerabilityItem[ 'actions' ] ),
				'management_link'  => [],
				'detail_table'     => [],
				'attention_items'  => [ $item ],
				'maintenance_rows' => [],
			];
		}

		return $seeds;
	}

	/**
	 * @return GroupSeed
	 */
	private function buildMaintenanceSeed( array $maintenanceItem, ?array $maintenanceRow ) :array {
		$rows = [];
		if ( $maintenanceRow !== null ) {
			$rows[] = $maintenanceRow;
		}

		return [
			'key'              => $maintenanceItem[ 'key' ],
			'definition_key'   => 'maintenance',
			'heading_label'    => '',
			'label'            => $maintenanceItem[ 'label' ],
			'item_count'       => (int)$maintenanceItem[ 'count' ],
			'status'           => StatusPriority::normalize( $maintenanceItem[ 'severity' ], 'warning' ),
			'narrative'        => $maintenanceItem[ 'description' ],
			'detail_shell'     => 'maintenance',
			'path_segments'    => [ $maintenanceItem[ 'label' ] ],
			'links'            => [],
			'management_link'  => $this->buildManagementLink( $maintenanceItem ),
			'detail_table'     => [],
			'attention_items'  => [],
			'maintenance_rows' => $rows,
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
	 * @param list<AssessmentRow> $maintenanceRows
	 * @return list<array{icon_class:string, title:string, summary:string}>
	 */
	private function buildMaintenanceItems( array $maintenanceRows ) :array {
		$items = [];
		foreach ( $maintenanceRows as $row ) {
			$items[] = [
				'icon_class' => $row[ 'status_icon_class' ],
				'title'      => $row[ 'label' ],
				'summary'    => $row[ 'description' ],
			];
		}
		return $items;
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

	private function buildNextMove( string $definitionKey ) :string {
		switch ( $definitionKey ) {
			case 'vulnerabilities':
				return __( 'Review the vulnerable assets first, then remove or replace anything abandoned.', 'wp-simple-firewall' );
			case 'wordpress':
				return __( 'Inspect the changed core files and repair them if they are not expected.', 'wp-simple-firewall' );
			case 'plugins':
			case 'themes':
				return __( 'Review the selected asset table for the fastest next action.', 'wp-simple-firewall' );
			case 'malware':
				return __( 'Review the flagged files and quarantine or delete them if they are confirmed malware.', 'wp-simple-firewall' );
			case 'file_locker':
				return __( 'Review the changed files and restore the locked originals if needed.', 'wp-simple-firewall' );
			case 'maintenance':
				return __( 'Review the maintenance item and address it in the next appropriate maintenance window.', 'wp-simple-firewall' );
			default:
				return __( 'Open this group to review the matching results.', 'wp-simple-firewall' );
		}
	}

	private function buildDrillHint( array $definition, int $itemCount, string $detailShell ) :string {
		if ( $itemCount < 1 || $detailShell === 'maintenance' ) {
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

	private function sectionOrderForGroup( array $group ) :int {
		$definitionKey = $this->definitionKeyForGroupKey( $group[ 'key' ] );
		return self::SECTION_ORDER[ $definitionKey ] ?? 999;
	}

	/**
	 * @param list<AssessmentRow> $maintenanceRows
	 * @return array<string,AssessmentRow>
	 */
	private function indexMaintenanceRowsByKey( array $maintenanceRows ) :array {
		$indexed = [];
		foreach ( $maintenanceRows as $row ) {
			$indexed[ $row[ 'key' ] ] = $row;
		}
		return $indexed;
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
			$this->pluginsPane = $this->buildActionsQueuePluginsPane();
		}

		return $this->pluginsPane;
	}

	/**
	 * @return QueueAssetPane
	 */
	private function themesPane() :array {
		if ( $this->themesPane === null ) {
			$this->themesPane = $this->buildActionsQueueThemesPane();
		}

		return $this->themesPane;
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

	protected function buildActionsQueuePluginsPane() :array {
		return ( new ScansResultsViewBuilder() )->buildActionsQueuePluginsPane();
	}

	protected function buildActionsQueueThemesPane() :array {
		return ( new ScansResultsViewBuilder() )->buildActionsQueueThemesPane();
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
}
