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
 * @phpstan-type GroupData array{
 *   key:string,
 *   label:string,
 *   item_count:int,
 *   status:string,
 *   icon_class:string,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   narrative:string,
 *   next_move:string,
 *   render_action_class:class-string<BaseAction>,
 *   render_action_data:array<string,string>,
 *   strip_text:string,
 *   strip_badge:string,
 *   context:LayerContext,
 *   selection:GroupSelection
 * }
 * @phpstan-type GroupsLayerData array{
 *   bucket_selection:BucketSelection,
 *   groups:list<GroupData>,
 *   context:LayerContext,
 *   strip_text:string,
 *   strip_badge:string,
 *   strip_badge_status:string
 * }
 * @phpstan-type BucketSource array{
 *   attention_items:list<AttentionItem>,
 *   maintenance_rows:list<AssessmentRow>,
 *   item_count:int
 * }
 * @phpstan-type GroupDefinition array{
 *   key:string,
 *   label:string,
 *   icon_class:string,
 *   detail_shell:'asset_cards'|'direct_table'|'maintenance',
 *   summary_keys:list<string>,
 *   render_action_class:class-string<BaseAction>,
 *   render_action_data:array<string,string>
 * }
 * @phpstan-type ResolvedGroup array{
 *   key:string,
 *   label:string,
 *   item_count:int,
 *   status:string,
 *   icon_class:string,
 *   attention_items:list<AttentionItem>,
 *   maintenance_rows:list<AssessmentRow>
 * }
 * @phpstan-type ComputedGroups array{
 *   layer:GroupsLayerData,
 *   groups_indexed:array<string,GroupData>
 * }
 */
class ActionsQueueGroupsBuilder {

	private ?ActionsQueueGroupDefinitions $groupDefinitions = null;
	private ?ActionsQueueDrillDownPresentationBuilder $presentation = null;

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
		$bucketSources = $bucketsBuilder->classify( $attentionQuery, $assessmentRowsByZone );
		$bucket = $buckets[ $bucketKey ];
		$groupsIndexed = $this->buildGroupsIndexedForBucket( $bucket[ 'label' ], $bucketSources[ $bucketKey ] );
		$groups = \array_values( $groupsIndexed );
		$bucketSelection = $bucket[ 'selection' ];

		return [
			'layer'          => [
				'bucket_selection'   => $bucketSelection,
				'groups'             => $groups,
				'context'            => $bucketSelection[ 'context' ],
				'strip_text'         => $bucketSelection[ 'strip_text' ],
				'strip_badge'        => $bucketSelection[ 'strip_badge' ],
				'strip_badge_status' => $bucketSelection[ 'status' ],
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
	 * @return array<string,GroupData>
	 */
	private function buildGroupsIndexedForBucket( string $bucketLabel, array $bucketSource ) :array {
		$groups = [];

		foreach ( $bucketSource[ 'attention_items' ] as $item ) {
			$groupKey = $this->groupDefinitions()->groupKeyForSummaryKey( $item[ 'key' ] );
			if ( !isset( $groups[ $groupKey ] ) ) {
				$definition = $this->getGroupDefinition( $groupKey );
				$groups[ $groupKey ] = [
					'key'              => $groupKey,
					'label'            => $definition[ 'label' ],
					'item_count'       => 0,
					'status'           => 'good',
					'icon_class'       => $definition[ 'icon_class' ],
					'attention_items'  => [],
					'maintenance_rows' => [],
				];
			}

			$groups[ $groupKey ][ 'item_count' ] += $item[ 'count' ];
			$groups[ $groupKey ][ 'status' ] = StatusPriority::highest( [
				$groups[ $groupKey ][ 'status' ],
				$item[ 'severity' ],
			], 'good' );
			$groups[ $groupKey ][ 'attention_items' ][] = $item;
		}

		if ( !empty( $bucketSource[ 'maintenance_rows' ] ) ) {
			$definition = $this->getGroupDefinition( 'maintenance' );
			$groups[ 'maintenance' ] = [
				'key'              => 'maintenance',
				'label'            => $definition[ 'label' ],
				'item_count'       => \count( $bucketSource[ 'maintenance_rows' ] ),
				'status'           => StatusPriority::highest( \array_column( $bucketSource[ 'maintenance_rows' ], 'status' ), 'good' ),
				'icon_class'       => $definition[ 'icon_class' ],
				'attention_items'  => [],
				'maintenance_rows' => $bucketSource[ 'maintenance_rows' ],
			];
		}

		\uasort( $groups, static function ( array $left, array $right ) :int {
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

		$resolved = [];
		foreach ( $groups as $group ) {
			$resolvedGroup = $this->resolveGroup( $bucketLabel, $group );
			$resolved[ $resolvedGroup[ 'key' ] ] = $resolvedGroup;
		}

		return $resolved;
	}

	/**
	 * @param ResolvedGroup $group
	 * @return GroupData
	 */
	private function resolveGroup( string $bucketLabel, array $group ) :array {
		$definition = $this->getGroupDefinition( $group[ 'key' ] );
		$narrative = $this->buildNarrative(
			$group[ 'key' ],
			$group[ 'attention_items' ],
			$group[ 'maintenance_rows' ],
			$group[ 'item_count' ]
		);
		$nextMove = $this->buildNextMove( $group[ 'key' ] );
		$context = [
			'path'      => [
				__( 'Triage buckets', 'wp-simple-firewall' ),
				$bucketLabel,
				$group[ 'label' ],
			],
			'focus'     => $narrative,
			'next_step' => $nextMove,
		];
		$selection = $this->presentation()->buildGroupSelection(
			$group[ 'key' ],
			$group[ 'label' ],
			$group[ 'status' ],
			$group[ 'item_count' ],
			$definition[ 'detail_shell' ],
			$context
		);

		return [
			'key'                 => $group[ 'key' ],
			'label'               => $group[ 'label' ],
			'item_count'          => $group[ 'item_count' ],
			'status'              => $group[ 'status' ],
			'icon_class'          => $group[ 'icon_class' ],
			'detail_shell'        => $definition[ 'detail_shell' ],
			'narrative'           => $narrative,
			'next_move'           => $nextMove,
			'render_action_class' => $definition[ 'render_action_class' ],
			'render_action_data'  => $definition[ 'render_action_data' ],
			'strip_text'          => $selection[ 'strip_text' ],
			'strip_badge'         => $selection[ 'strip_badge' ],
			'context'             => $context,
			'selection'           => $selection,
		];
	}

	/**
	 * @return GroupData
	 */
	private function buildEmptyGroup( string $groupKey, string $bucketLabel ) :array {
		$definition = $this->getGroupDefinition( $groupKey );
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
			'label'               => $definition[ 'label' ],
			'item_count'          => 0,
			'status'              => 'good',
			'icon_class'          => $definition[ 'icon_class' ],
			'detail_shell'        => $definition[ 'detail_shell' ],
			'narrative'           => $narrative,
			'next_move'           => $nextMove,
			'render_action_class' => $definition[ 'render_action_class' ],
			'render_action_data'  => $definition[ 'render_action_data' ],
			'strip_text'          => $selection[ 'strip_text' ],
			'strip_badge'         => $selection[ 'strip_badge' ],
			'context'             => $context,
			'selection'           => $selection,
		];
	}

	/**
	 * @param list<AttentionItem> $attentionItems
	 * @param list<AssessmentRow> $maintenanceRows
	 */
	private function buildNarrative( string $groupKey, array $attentionItems, array $maintenanceRows, int $itemCount ) :string {
		switch ( $groupKey ) {
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
					_n( '%s plugin file group needs review.', '%s plugin file groups need review.', $itemCount, 'wp-simple-firewall' ),
					$itemCount
				);

			case 'themes':
				return \sprintf(
					_n( '%s theme file group needs review.', '%s theme file groups need review.', $itemCount, 'wp-simple-firewall' ),
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
				if ( !empty( $maintenanceRows ) ) {
					return \sprintf(
						_n( '%s maintenance check is currently healthy.', '%s maintenance checks are currently healthy.', $itemCount, 'wp-simple-firewall' ),
						$itemCount
					);
				}
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

	private function buildNextMove( string $groupKey ) :string {
		switch ( $groupKey ) {
			case 'vulnerabilities':
				return __( 'Review the vulnerable assets first, then remove or replace anything abandoned.', 'wp-simple-firewall' );
			case 'wordpress':
				return __( 'Inspect the changed core files and repair them if they are not expected.', 'wp-simple-firewall' );
			case 'plugins':
			case 'themes':
				return __( 'Open the affected assets and review their file tables for the fastest next action.', 'wp-simple-firewall' );
			case 'malware':
				return __( 'Review the flagged files and quarantine or delete them if they are confirmed malware.', 'wp-simple-firewall' );
			case 'file_locker':
				return __( 'Review the changed files and restore the locked originals if needed.', 'wp-simple-firewall' );
			case 'maintenance':
				return __( 'Review the maintenance items and address them in the next appropriate maintenance window.', 'wp-simple-firewall' );
			default:
				return __( 'Open this group to review the matching results.', 'wp-simple-firewall' );
		}
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
	 * @return GroupDefinition
	 */
	private function getGroupDefinition( string $groupKey ) :array {
		return $this->groupDefinitions()->all()[ $groupKey ];
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
