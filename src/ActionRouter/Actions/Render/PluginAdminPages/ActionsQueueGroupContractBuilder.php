<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type GroupData from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupSectionData from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupLink from ActionsQueueGroupsBuilder
 * @phpstan-import-type GroupManagementLink from ActionsQueueGroupsBuilder
 * @phpstan-import-type CompactSummaryRow from ActionsQueueCompactSummaryRowBuilder
 * @phpstan-import-type GroupDefinition from ActionsQueueGroupDefinitions
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
 *   render_action_class_override?:class-string<\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction>,
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
 */
class ActionsQueueGroupContractBuilder {

	use StandardStatusMapping;

	private ActionsQueueGroupDefinitions $groupDefinitions;
	private ActionsQueueDrillDownPresentationBuilder $presentation;
	private ActionsQueueAssetMetadataResolver $assetMetadataResolver;
	private ActionsQueueScanResultsOptions $queueScanResultsOptions;

	public function __construct(
		ActionsQueueGroupDefinitions $groupDefinitions,
		ActionsQueueDrillDownPresentationBuilder $presentation,
		?ActionsQueueAssetMetadataResolver $assetMetadataResolver = null,
		?ActionsQueueScanResultsOptions $queueScanResultsOptions = null
	) {
		$this->groupDefinitions = $groupDefinitions;
		$this->presentation = $presentation;
		$this->assetMetadataResolver = $assetMetadataResolver ?? new ActionsQueueAssetMetadataResolver();
		$this->queueScanResultsOptions = $queueScanResultsOptions ?? new ActionsQueueScanResultsOptions();
	}

	/**
	 * @param list<GroupSeed> $resolvedSeeds
	 * @return ResolvedGroups
	 */
	public function buildResolvedGroups( string $bucketLabel, array $resolvedSeeds ) :array {
		$this->sortSeeds( $resolvedSeeds );
		$partitionedGroups = $this->partitionResolvedGroups( $bucketLabel, $resolvedSeeds );

		return [
			'groups_indexed'   => $partitionedGroups[ 'groups_indexed' ],
			'active_sections'  => $this->buildSectionsFromEntries( $partitionedGroups[ 'active_entries' ] ),
			'healthy_sections' => $this->buildSectionsFromEntries( $partitionedGroups[ 'healthy_entries' ] ),
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
		$selection = $this->presentation->buildGroupSelection(
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
			'render_action_data'  => $definition[ 'render_action_data' ],
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
		$selection = $this->presentation->buildGroupSelection(
			$bucketLabel,
			$groupKey,
			$metadata[ 'title' ],
			'good',
			$metadata[ 'icon_class' ],
			0,
			'direct_table',
			$narrative
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
			'render_action_data'  => [
				'subject_type'            => $metadata[ 'subject_type' ],
				'subject_id'              => $metadata[ 'subject_id' ],
				'results_display_options' => $this->queueScanResultsOptions->activeOnly(),
			],
			'maintenance_rows'    => [],
			'summary_row'         => [],
			'selection'           => $selection,
		];
	}

	/**
	 * @param list<GroupSeed> $resolvedSeeds
	 */
	private function sortSeeds( array &$resolvedSeeds ) :void {
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
	}

	/**
	 * @param list<GroupSeed> $resolvedSeeds
	 * @return array{
	 *   groups_indexed:array<string,GroupData>,
	 *   active_entries:list<array{heading_label:string,group:GroupData}>,
	 *   healthy_entries:list<array{heading_label:string,group:GroupData}>
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
				'heading_label' => $seed[ 'heading_label' ],
				'group'         => $group,
			];
			if ( $seed[ 'is_healthy' ] ) {
				$healthyEntries[] = $entry;
			}
			else {
				$activeEntries[] = $entry;
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
		$selection = $this->presentation->buildGroupSelection(
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
			'status_label'        => $this->standardStatusLabel( $seed[ 'status' ] ),
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
			'render_action_class' => $seed[ 'render_action_class_override' ]
				?? $definition[ 'render_action_class' ],
			'render_action_data'  => $seed[ 'render_action_data_override' ]
				?? $definition[ 'render_action_data' ],
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

	/**
	 * @phpstan-param GroupDefinition $definition
	 */
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
	 * @phpstan-param GroupSeed $seed
	 */
	private function definitionOrderForSeed( array $seed ) :int {
		return $this->groupDefinitions->sortOrderForGroupKey( $seed[ 'definition_key' ] );
	}

	/**
	 * @phpstan-param GroupSeed $seed
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
	 * @phpstan-param GroupSeed $seed
	 */
	private function determineInteractivity( array $seed ) :bool {
		return ( $seed[ 'detail_shell' ] ?? '' ) !== 'maintenance'
			&& ( $seed[ 'card_type_override' ] ?? '' ) !== 'linked'
			&& (
				!empty( $seed[ 'detail_table' ] )
				|| !empty( $seed[ 'render_action_class_override' ] )
				|| !empty( $seed[ 'render_action_data_override' ] )
				|| ( $seed[ 'item_count' ] ?? 0 ) > 0
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

}
