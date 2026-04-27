<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 * @phpstan-type DetailActionData array<string,string>
 * @phpstan-type DetailAction array{
 *   label:string,
 *   href:string,
 *   title:string,
 *   target:string,
 *   icon:string,
 *   classes:list<string>,
 *   data:DetailActionData
 * }
 * @phpstan-type DetailActionInput array{
 *   label?:string,
 *   href?:string,
 *   title?:string,
 *   target?:string,
 *   icon?:string,
 *   classes?:list<string>,
 *   data?:DetailActionData
 * }
 * @phpstan-type MaintenanceIssueItem array{
 *   key:string,
 *   label:string,
 *   description:string,
 *   count:int,
 *   severity:string,
 *   cta?:DetailActionInput
 * }
 * @phpstan-type ConfigureRow array{
 *   key:string,
 *   title:string,
 *   note:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   explanations:list<string>,
 *   config_action:array{}|DetailAction
 * }
 * @phpstan-type DetailGroupRow array{
 *   key:string,
 *   title:string,
 *   summary:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   count_badge:?int,
 *   badge_status:string,
 *   explanations:list<string>,
 *   action:array{}|DetailAction,
 *   is_expandable:bool
 * }
 * @phpstan-type SortableDetailGroupRow array{
 *   key:string,
 *   title:string,
 *   summary:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   count_badge:?int,
 *   badge_status:string,
 *   explanations:list<string>,
 *   action:array{}|DetailAction,
 *   is_expandable:bool,
 *   sort_index:int
 * }
 * @phpstan-type DetailGroup array{
 *   status:string,
 *   rows:list<DetailGroupRow>
 * }
 */
class StatusDetailGroupsBuilder {

	use StandardStatusMapping;

	/**
	 * @param list<MaintenanceIssueItem> $issueItems
	 * @param list<AssessmentRow> $assessmentRows
	 * @return list<DetailGroup>
	 */
	public function buildForMaintenance( array $issueItems, array $assessmentRows ) :array {
		$rows = [];
		$index = 0;

		foreach ( $issueItems as $item ) {
			$rows[] = $this->buildMaintenanceIssueRow( $item, $index++ );
		}

		foreach ( $assessmentRows as $row ) {
			if ( $row[ 'status' ] !== 'good' ) {
				continue;
			}
			$rows[] = $this->buildAssessmentRow( $row, $index++ );
		}

		return $this->groupRows( $rows );
	}

	/**
	 * @param list<ConfigureRow> $rows
	 * @return list<DetailGroup>
	 */
	public function buildForConfigure( array $rows ) :array {
		$detailRows = [];

		foreach ( \array_values( $rows ) as $index => $row ) {
			$detailRows[] = $this->buildConfigureRow( $row, $index );
		}

		return $this->groupRows( $detailRows );
	}

	/**
	 * @param MaintenanceIssueItem $item
	 * @return SortableDetailGroupRow
	 */
	private function buildMaintenanceIssueRow( array $item, int $sortIndex ) :array {
		$status = $this->normalizeStatus( $item[ 'severity' ] );
		$action = isset( $item[ 'cta' ] ) ? $this->normalizeAction( $item[ 'cta' ], '' ) : [];

		return [
			'key'               => $item[ 'key' ],
			'title'             => $item[ 'label' ],
			'summary'           => $item[ 'description' ],
			'status'            => $status,
			'status_label'      => $this->standardStatusLabel( $status ),
			'status_icon_class' => $this->standardStatusIconClass( $status ),
			'count_badge'       => $item[ 'count' ],
			'badge_status'      => $this->badgeStatus( $status ),
			'explanations'      => [],
			'action'            => $action,
			'is_expandable'     => $this->isExpandableAction( $action ),
			'sort_index'        => $sortIndex,
		];
	}

	/**
	 * @param AssessmentRow $row
	 * @return SortableDetailGroupRow
	 */
	private function buildAssessmentRow( array $row, int $sortIndex ) :array {
		$status = $this->normalizeStatus( $row[ 'status' ] );

		return [
			'key'               => $row[ 'key' ],
			'title'             => $row[ 'label' ],
			'summary'           => $row[ 'description' ],
			'status'            => $status,
			'status_label'      => $row[ 'status_label' ],
			'status_icon_class' => $row[ 'status_icon_class' ],
			'count_badge'       => null,
			'badge_status'      => $this->badgeStatus( $status ),
			'explanations'      => [],
			'action'            => [],
			'is_expandable'     => false,
			'sort_index'        => $sortIndex,
		];
	}

	/**
	 * @param ConfigureRow $row
	 * @return SortableDetailGroupRow
	 */
	private function buildConfigureRow( array $row, int $sortIndex ) :array {
		$status = $this->normalizeStatus( $row[ 'status' ] );
		$action = $row[ 'config_action' ];

		return [
			'key'               => $row[ 'key' ],
			'title'             => $row[ 'title' ],
			'summary'           => $row[ 'note' ],
			'status'            => $status,
			'status_label'      => $row[ 'status_label' ],
			'status_icon_class' => $row[ 'status_icon_class' ],
			'count_badge'       => null,
			'badge_status'      => $this->badgeStatus( $status ),
			'explanations'      => $row[ 'explanations' ],
			'action'            => $action,
			'is_expandable'     => $this->isExpandableAction( $action ),
			'sort_index'        => $sortIndex,
		];
	}

	/**
	 * @param list<SortableDetailGroupRow> $rows
	 * @return list<DetailGroup>
	 */
	private function groupRows( array $rows ) :array {
		\usort( $rows, function ( array $a, array $b ) :int {
			$rankCmp = $this->detailStatusRank( $b[ 'status' ] )
				<=> $this->detailStatusRank( $a[ 'status' ] );
			if ( $rankCmp !== 0 ) {
				return $rankCmp;
			}

			return $a[ 'sort_index' ] <=> $b[ 'sort_index' ];
		} );

		$groups = [];
		foreach ( $rows as $row ) {
			$status = $row[ 'status' ];
			if ( empty( $groups ) || $groups[ \count( $groups ) - 1 ][ 'status' ] !== $status ) {
				$groups[] = [
					'status' => $status,
					'rows'   => [],
				];
			}
			$groups[ \count( $groups ) - 1 ][ 'rows' ][] = $this->withoutSortIndex( $row );
		}

		return $groups;
	}

	/**
	 * @param SortableDetailGroupRow $row
	 * @return DetailGroupRow
	 */
	private function withoutSortIndex( array $row ) :array {
		return [
			'key'               => $row[ 'key' ],
			'title'             => $row[ 'title' ],
			'summary'           => $row[ 'summary' ],
			'status'            => $row[ 'status' ],
			'status_label'      => $row[ 'status_label' ],
			'status_icon_class' => $row[ 'status_icon_class' ],
			'count_badge'       => $row[ 'count_badge' ],
			'badge_status'      => $row[ 'badge_status' ],
			'explanations'      => $row[ 'explanations' ],
			'action'            => $row[ 'action' ],
			'is_expandable'     => $row[ 'is_expandable' ],
		];
	}

	/**
	 * @param DetailActionInput|null $action
	 * @return array{}|DetailAction
	 */
	private function normalizeAction( ?array $action, string $defaultLabel ) :array {
		if ( empty( $action ) ) {
			return [];
		}

		$data = isset( $action[ 'data' ] ) ? $action[ 'data' ] : [];
		if ( !\is_array( $data ) ) {
			$data = [];
		}
		$classesInput = isset( $action[ 'classes' ] ) ? $action[ 'classes' ] : [];
		$classes = \array_values( \array_filter(
			\array_map( static fn( $class ) :string => \trim( (string)$class ), $classesInput ),
			static fn( string $class ) :bool => $class !== ''
		) );

		return [
			'label'   => $action[ 'label' ] ?? $defaultLabel,
			'href'    => $action[ 'href' ] ?? 'javascript:{}',
			'title'   => $action[ 'title' ] ?? '',
			'target'  => $action[ 'target' ] ?? '',
			'icon'    => $action[ 'icon' ] ?? '',
			'classes' => $classes,
			'data'    => $this->normalizeActionDataAttributes( $data ),
		];
	}

	/**
	 * @param array<mixed> $data
	 * @return DetailActionData
	 */
	private function normalizeActionDataAttributes( array $data ) :array {
		$normalized = [];
		foreach ( $data as $key => $value ) {
			$attribute = sanitize_key( (string)$key );
			if ( $attribute === '' ) {
				continue;
			}
			$normalized[ $attribute ] = (string)$value;
		}
		return $normalized;
	}

	/**
	 * @param array{}|DetailAction $action
	 */
	private function isExpandableAction( array $action ) :bool {
		return !empty( $action[ 'data' ][ 'zone_component_slug' ] )
			&& !empty( $action[ 'data' ][ 'zone_component_action' ] );
	}

	private function normalizeStatus( string $status ) :string {
		$status = \strtolower( \trim( $status ) );
		return $status === 'neutral' ? 'neutral' : StatusPriority::normalize( $status, 'good' );
	}

	private function badgeStatus( string $status ) :string {
		return $status === 'neutral' ? 'info' : $status;
	}

	private function detailStatusRank( string $status ) :int {
		switch ( $this->normalizeStatus( $status ) ) {
			case 'critical':
				return 3;
			case 'warning':
				return 2;
			case 'good':
				return 1;
			case 'neutral':
			default:
				return 0;
		}
	}
}
