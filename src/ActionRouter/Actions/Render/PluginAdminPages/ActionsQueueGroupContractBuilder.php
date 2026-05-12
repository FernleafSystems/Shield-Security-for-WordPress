<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type GroupData from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupSectionData from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupLink from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupManagementLink from ActionsQueueGroupsBuilder
 * @phpstan-import-type CompactSummaryRow from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-import-type OperatorChromeActionInput from OperatorChromeContract
 * @phpstan-type GroupSeed array{
 *   key:string,
 *   definition_key:string,
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
 *   status_label_override?:string,
 *   header_summary_override?:string,
 *   header_focus_override?:string,
 *   header_next_step_override?:string,
 *   header_badge_override?:string,
 *   header_badge_status_override?:string,
 *   header_color_key_override?:string,
 *   context_actions_override?:list<OperatorChromeActionInput>,
 *   detail_table:array<string,mixed>,
 *   render_action_class_override?:class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender>,
 *   render_action_data_override?:array<string,mixed>,
 *   attention_items:list<AttentionItem>,
 *   maintenance_rows:list<CompactSummaryRow>,
 *   summary_row:array{}|CompactSummaryRow
 * }
 * @phpstan-type ResolvedGroups array{
 *   groups_indexed:array<string,GroupData>,
 *   active_sections:list<GroupSectionData>,
 *   healthy_sections:list<GroupSectionData>
 * }
 * @phpstan-type GroupSectionEntry array{
 *   section_key:string,
 *   heading_label:string,
 *   group:GroupData
 * }
 */
class ActionsQueueGroupContractBuilder {

	use StandardStatusMapping;

	private ActionsQueueGroupDefinitions $groupDefinitions;
	private ActionsQueueDrillDownPresentationBuilder $presentation;
	private ActionsQueueAssetMetadataResolver $assetMetadataResolver;
	private ScanResultsDisplayOptions $queueScanResultsOptions;
	private ActionsQueueContextActionsBuilder $contextActionsBuilder;

	public function __construct(
		ActionsQueueGroupDefinitions $groupDefinitions,
		ActionsQueueDrillDownPresentationBuilder $presentation,
		?ActionsQueueAssetMetadataResolver $assetMetadataResolver = null,
		?ScanResultsDisplayOptions $queueScanResultsOptions = null,
		?ActionsQueueContextActionsBuilder $contextActionsBuilder = null
	) {
		$this->groupDefinitions = $groupDefinitions;
		$this->presentation = $presentation;
		$this->assetMetadataResolver = $assetMetadataResolver ?? new ActionsQueueAssetMetadataResolver();
		$this->queueScanResultsOptions = $queueScanResultsOptions ?? new ScanResultsDisplayOptions();
		$this->contextActionsBuilder = $contextActionsBuilder ?? new ActionsQueueContextActionsBuilder(
			$this->queueScanResultsOptions
		);
	}

	/**
	 * @param list<GroupSeed> $resolvedSeeds
	 * @return ResolvedGroups
	 */
	public function buildResolvedGroups( string $bucketLabel, array $resolvedSeeds ) :array {
		$partitionedGroups = $this->partitionResolvedGroups( $bucketLabel, $resolvedSeeds );

		return [
			'groups_indexed'   => $partitionedGroups[ 'groups_indexed' ],
			'active_sections'  => $this->buildActiveSections( $partitionedGroups[ 'active_entries' ] ),
			'healthy_sections' => $this->buildHealthySections( $partitionedGroups[ 'healthy_entries' ] ),
		];
	}

	/**
	 * @return GroupData
	 */
	public function buildEmptyGroup( string $groupKey, string $bucketLabel ) :array {
		$assetGroup = $this->buildEmptyScopedAssetGroup( $groupKey, $bucketLabel );
		if ( $assetGroup !== null ) {
			return $assetGroup;
		}

		return $this->buildDefinitionEmptyGroup( $groupKey, $bucketLabel );
	}

	/**
	 * @return GroupData
	 */
	private function buildDefinitionEmptyGroup( string $groupKey, string $bucketLabel ) :array {
		$definition = $this->groupDefinitions->definitionForGroupKey( $groupKey );
		$narrative = __( 'No matching items remain in this group.', 'wp-simple-firewall' );
		$renderActionData = $definition[ 'render_action_data' ];
		$selection = $this->presentation->buildGroupSelection(
			$bucketLabel,
			$groupKey,
			$definition[ 'label' ],
			'good',
			$definition[ 'icon_class' ],
			0,
			$definition[ 'detail_shell' ],
			$this->buildDetailRenderAction(
				$definition[ 'render_action_class' ],
				$renderActionData
			),
			$narrative,
			[]
		);

		return [
			'key'                 => $groupKey,
			'label'               => $definition[ 'label' ],
			'item_count'          => 0,
			'status'              => 'good',
			'status_label'        => $this->standardStatusLabel( 'good' ),
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
			'render_action_data'  => $renderActionData,
			'maintenance_rows'    => [],
			'summary_row'         => [],
			'selection'           => $selection,
		];
	}

	/**
	 * @return GroupData|null
	 */
	private function buildEmptyScopedAssetGroup( string $groupKey, string $bucketLabel ) :?array {
		[ 'definition_key' => $definitionKey, 'asset_key' => $assetKey ] = $this->parseScopedAssetGroupKey( $groupKey );
		if ( $definitionKey === '' || $assetKey === '' ) {
			return null;
		}

		$assetType = $definitionKey === 'plugins' ? 'plugin' : 'theme';
		$metadata = $this->assetMetadataResolver->resolve( $assetType, $assetKey );
		if ( $metadata === null ) {
			return null;
		}

		$narrative = __( 'No matching items remain in this group.', 'wp-simple-firewall' );
		$renderActionData = $this->queueScanResultsOptions->buildSubjectActionData(
			$metadata[ 'subject_type' ],
			$metadata[ 'subject_id' ]
		);
		$selection = $this->presentation->buildGroupSelection(
			$bucketLabel,
			$groupKey,
			$metadata[ 'title' ],
			'good',
			$metadata[ 'icon_class' ],
			0,
			'direct_table',
			$this->buildDetailRenderAction(
				ActionsQueueAssetFileStatusDetail::class,
				$renderActionData
			),
			$narrative,
			[]
		);

		return [
			'key'                 => $groupKey,
			'label'               => $metadata[ 'title' ],
			'item_count'          => 0,
			'status'              => 'good',
			'status_label'        => $this->standardStatusLabel( 'good' ),
			'icon_class'          => $metadata[ 'icon_class' ],
			'detail_shell'        => 'direct_table',
			'card_type'           => 'expandable',
			'narrative'           => $narrative,
			'drill_hint'          => '',
			'links'               => [],
			'management_link'     => [],
			'is_interactive'      => false,
			'detail_table'        => [],
			'render_action_class' => ActionsQueueAssetFileStatusDetail::class,
			'render_action_data'  => $renderActionData,
			'maintenance_rows'    => [],
			'summary_row'         => [],
			'selection'           => $selection,
		];
	}

	/**
	 * @param list<GroupSeed> $resolvedSeeds
	 * @return array{
	 *   groups_indexed:array<string,GroupData>,
	 *   active_entries:list<GroupSectionEntry>,
	 *   healthy_entries:list<GroupSectionEntry>
	 * }
	 */
	private function partitionResolvedGroups( string $bucketLabel, array $resolvedSeeds ) :array {
		$indexed = [];
		$activeEntries = [];
		$healthyEntries = [];

		foreach ( $resolvedSeeds as $seed ) {
			$group = $this->resolveSeed( $bucketLabel, $seed );
			$indexed[ $group[ 'key' ] ] = $group;
			$entry = [
				'section_key'   => $this->groupDefinitions->sectionKeyForGroupKey( $seed[ 'definition_key' ] ),
				'heading_label' => $this->groupDefinitions->sectionLabelForGroupKey( $seed[ 'definition_key' ] ),
				'group'         => $group,
			];
			if ( $this->isActiveStatus( $group[ 'status' ] ) ) {
				$activeEntries[] = $entry;
			}
			else {
				$healthyEntries[] = $entry;
			}
		}

		return [
			'groups_indexed'  => $indexed,
			'active_entries'  => $activeEntries,
			'healthy_entries' => $healthyEntries,
		];
	}

	/**
	 * @phpstan-param GroupSeed $seed
	 * @return GroupData
	 */
	private function resolveSeed( string $bucketLabel, array $seed ) :array {
		$definition = $this->groupDefinitions->definitionForGroupKey( $seed[ 'definition_key' ] );
		$iconClass = $seed[ 'icon_class_override' ] ?? $definition[ 'icon_class' ];
		$narrative = $seed[ 'narrative' ] !== ''
			? $seed[ 'narrative' ]
			: $this->buildNarrative( $seed[ 'definition_key' ], $seed[ 'attention_items' ], $seed[ 'item_count' ] );
		$isInteractive = $seed[ 'is_interactive_override' ]
			?? $this->determineInteractivity( $seed );
		$renderActionData = $seed[ 'render_action_data_override' ]
			?? $definition[ 'render_action_data' ];
		$contextActions = \array_key_exists( 'context_actions_override', $seed )
			? $seed[ 'context_actions_override' ]
			: $this->contextActionsBuilder->buildForGroup(
				$seed[ 'definition_key' ],
				$seed[ 'label' ],
				$seed[ 'detail_shell' ],
				$seed[ 'item_count' ],
				$renderActionData
			);
		$headerOverrides = \array_filter( [
			'summary'      => $seed[ 'header_summary_override' ] ?? '',
			'focus'        => $seed[ 'header_focus_override' ] ?? '',
			'next_step'    => $seed[ 'header_next_step_override' ] ?? '',
			'badge'        => $seed[ 'header_badge_override' ] ?? '',
			'badge_status' => $seed[ 'header_badge_status_override' ] ?? '',
			'color_key'    => $seed[ 'header_color_key_override' ] ?? '',
		], static fn( string $value ) :bool => $value !== '' );
		$selection = $this->presentation->buildGroupSelection(
			$bucketLabel,
			$seed[ 'key' ],
			$seed[ 'label' ],
			$seed[ 'status' ],
			$iconClass,
			$seed[ 'item_count' ],
			$seed[ 'detail_shell' ],
			$this->buildDetailRenderAction(
				$seed[ 'render_action_class_override' ]
					?? $definition[ 'render_action_class' ],
				$renderActionData
			),
			$narrative,
			$contextActions,
			$headerOverrides
		);

		return [
			'key'                 => $seed[ 'key' ],
			'label'               => $seed[ 'label' ],
			'item_count'          => $seed[ 'item_count' ],
			'status'              => $seed[ 'status' ],
			'status_label'        => $seed[ 'status_label_override' ] ?? $this->standardStatusLabel( $seed[ 'status' ] ),
			'icon_class'          => $iconClass,
			'detail_shell'        => $seed[ 'detail_shell' ],
			'card_type'           => $seed[ 'card_type_override' ] ?? $definition[ 'card_type' ],
			'narrative'           => $narrative,
			'drill_hint'          => $this->buildDrillHint(
				$seed[ 'definition_key' ],
				$seed[ 'item_count' ],
				$seed[ 'detail_shell' ],
				$seed[ 'status' ]
			),
			'links'               => $seed[ 'links' ],
			'management_link'     => $seed[ 'management_link' ],
			'is_interactive'      => $isInteractive,
			'detail_table'        => $seed[ 'detail_table' ],
			'render_action_class' => $seed[ 'render_action_class_override' ]
				?? $definition[ 'render_action_class' ],
			'render_action_data'  => $renderActionData,
			'maintenance_rows'    => $seed[ 'maintenance_rows' ],
			'summary_row'         => $seed[ 'summary_row' ],
			'selection'           => $selection,
		];
	}

	/**
	 * @param list<AttentionItem> $attentionItems
	 */
	private function buildNarrative( string $definitionKey, array $attentionItems, int $itemCount ) :string {
		switch ( $definitionKey ) {
			case 'vulnerabilities':
				$vulnerableCount = \max( $itemCount, $this->countAttentionItemsByKey( $attentionItems, 'vulnerable_assets' ) );
				return \sprintf(
					_n( '%s vulnerable asset needs review.', '%s vulnerable assets need review.', $vulnerableCount, 'wp-simple-firewall' ),
					$vulnerableCount
				);

			case 'abandoned':
				$abandonedCount = \max( $itemCount, $this->countAttentionItemsByKey( $attentionItems, 'abandoned' ) );
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

	private function buildDrillHint( string $definitionKey, int $itemCount, string $detailShell, string $status ) :string {
		if ( $itemCount < 1 || $detailShell === 'maintenance' || $status === 'good' ) {
			return '';
		}

		switch ( $definitionKey ) {
			case 'wordpress':
			case 'plugins':
			case 'themes':
			case 'malware':
			case 'file_locker':
				$pattern = _n( 'View %s file', 'View %s files', $itemCount, 'wp-simple-firewall' );
				break;

			default:
				return '';
		}

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

	private function buildActiveSections( array $entries ) :array {
		$sections = $this->buildSectionsMap( $entries );
		\usort( $sections, fn( array $left, array $right ) :int => $this->compareActiveSections( $left, $right ) );

		return \array_values( \array_map( function ( array $section ) :array {
			\usort( $section[ 'groups' ], fn( array $left, array $right ) :int => $this->compareGroups( $left, $right, false ) );
			return [
				'heading_label' => $section[ 'heading_label' ],
				'groups'        => $section[ 'groups' ],
			];
		}, $sections ) );
	}

	/**
	 * @param list<GroupSectionEntry> $entries
	 * @return list<GroupSectionData>
	 */
	private function buildHealthySections( array $entries ) :array {
		\usort( $entries, fn( array $left, array $right ) :int => $this->compareHealthyEntries( $left, $right ) );
		$sections = $this->buildSectionsMap( $entries );

		return \array_values( \array_map( function ( array $section ) :array {
			return [
				'heading_label' => $section[ 'heading_label' ],
				'groups'        => $section[ 'groups' ],
			];
		}, $sections ) );
	}

	/**
	 * @param list<GroupSectionEntry> $entries
	 * @return list<array{
	 *   section_key:string,
	 *   heading_label:string,
	 *   groups:list<GroupData>
	 * }>
	 */
	private function buildSectionsMap( array $entries ) :array {
		$sections = [];
		foreach ( $entries as $entry ) {
			$sectionKey = $entry[ 'section_key' ];
			if ( !isset( $sections[ $sectionKey ] ) ) {
				$sections[ $sectionKey ] = [
					'section_key'    => $sectionKey,
					'heading_label'  => $entry[ 'heading_label' ],
					'groups'        => [],
				];
			}
			$sections[ $sectionKey ][ 'groups' ][] = $entry[ 'group' ];
		}

		return \array_values( $sections );
	}

	/**
	 * @param array{section_key:string,heading_label:string,groups:list<GroupData>} $left
	 * @param array{section_key:string,heading_label:string,groups:list<GroupData>} $right
	 */
	private function compareActiveSections( array $left, array $right ) :int {
		$sectionCmp = $this->sectionOrderForSectionKey( $left[ 'section_key' ] ) <=> $this->sectionOrderForSectionKey( $right[ 'section_key' ] );
		if ( $sectionCmp !== 0 ) {
			return $sectionCmp;
		}

		return \strcmp( $left[ 'heading_label' ], $right[ 'heading_label' ] );
	}

	/**
	 * @phpstan-param GroupSectionEntry $left
	 * @phpstan-param GroupSectionEntry $right
	 */
	private function compareHealthyEntries( array $left, array $right ) :int {
		$healthyCmp = $this->healthyGroupOrder( $left[ 'group' ][ 'status' ] ) <=> $this->healthyGroupOrder( $right[ 'group' ][ 'status' ] );
		if ( $healthyCmp !== 0 ) {
			return $healthyCmp;
		}

		$sectionCmp = $this->sectionOrderForSectionKey( $left[ 'section_key' ] ) <=> $this->sectionOrderForSectionKey( $right[ 'section_key' ] );
		if ( $sectionCmp !== 0 ) {
			return $sectionCmp;
		}

		if ( $left[ 'section_key' ] !== $right[ 'section_key' ] ) {
			return \strcmp( $left[ 'heading_label' ], $right[ 'heading_label' ] );
		}

		return $this->compareGroups( $left[ 'group' ], $right[ 'group' ], true );
	}

	/**
	 * @phpstan-param GroupData $left
	 * @phpstan-param GroupData $right
	 */
	private function compareGroups( array $left, array $right, bool $healthy ) :int {
		if ( $healthy ) {
			$healthyCmp = $this->healthyGroupOrder( $left[ 'status' ] ) <=> $this->healthyGroupOrder( $right[ 'status' ] );
			if ( $healthyCmp !== 0 ) {
				return $healthyCmp;
			}
		}
		else {
			$activeCmp = $this->activeGroupOrder( $left[ 'status' ] ) <=> $this->activeGroupOrder( $right[ 'status' ] );
			if ( $activeCmp !== 0 ) {
				return $activeCmp;
			}
		}

		$orderCmp = $this->groupDefinitions->sortOrderForGroupKey( $left[ 'key' ] ) <=> $this->groupDefinitions->sortOrderForGroupKey( $right[ 'key' ] );
		if ( $orderCmp !== 0 ) {
			return $orderCmp;
		}

		$countCmp = $right[ 'item_count' ] <=> $left[ 'item_count' ];
		if ( $countCmp !== 0 ) {
			return $countCmp;
		}

		return \strcmp( $left[ 'label' ], $right[ 'label' ] );
	}

	private function sectionOrderForSectionKey( string $sectionKey ) :int {
		if ( $sectionKey === '' ) {
			return 999;
		}

		return $this->groupDefinitions->sectionOrderForGroupKey( $sectionKey );
	}

	private function isActiveStatus( string $status ) :bool {
		return \in_array( StatusPriority::normalize( $status, 'info' ), [ 'critical', 'warning' ], true );
	}

	private function healthyGroupOrder( string $status ) :int {
		return StatusPriority::normalize( $status, 'info' ) === 'good' ? 0 : 1;
	}

	private function activeGroupOrder( string $status ) :int {
		return StatusPriority::normalize( $status, 'warning' ) === 'critical' ? 0 : 1;
	}

	/**
	 * @phpstan-param GroupSeed $seed
	 */
	private function determineInteractivity( array $seed ) :bool {
		return $seed[ 'detail_shell' ] !== 'maintenance'
			&& ( $seed[ 'card_type_override' ] ?? '' ) !== 'linked'
			&& (
				!empty( $seed[ 'detail_table' ] )
				|| !empty( $seed[ 'render_action_class_override' ] )
				|| !empty( $seed[ 'render_action_data_override' ] )
				|| $seed[ 'item_count' ] > 0
			);
	}

	/**
	 * @return array{definition_key:string,asset_key:string}
	 */
	private function parseScopedAssetGroupKey( string $groupKey ) :array {
		$definitionKey = \strstr( $groupKey, ':', true );
		if ( !\is_string( $definitionKey ) || !\in_array( $definitionKey, [ 'plugins', 'themes' ], true ) ) {
			return [
				'definition_key' => '',
				'asset_key'      => '',
			];
		}

		return [
			'definition_key' => $definitionKey,
			'asset_key'      => \ltrim( (string)\substr( $groupKey, \strlen( $definitionKey ) + 1 ), ':' ),
		];
	}

	/**
	 * @param class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender> $renderActionClass
	 * @param array<string,mixed> $renderActionData
	 * @return array<string,mixed>
	 */
	private function buildDetailRenderAction( string $renderActionClass, array $renderActionData ) :array {
		return ActionData::BuildAjaxRender( $renderActionClass, $renderActionData );
	}

}
