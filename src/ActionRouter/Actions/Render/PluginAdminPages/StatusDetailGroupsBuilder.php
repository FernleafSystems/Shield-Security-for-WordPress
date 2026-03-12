<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

/**
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
 * @phpstan-type AssessmentRow array{
 *   key:string,
 *   label:string,
 *   description:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string
 * }
 * @phpstan-type ConfigureComponentRow array{
 *   title:string,
 *   note:string,
 *   status:string,
 *   status_label:string,
 *   status_icon_class:string,
 *   explanations:list<string>,
 *   config_action:DetailActionInput
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
 *   sort_index:int
 * }
 * @phpstan-type DetailGroup array{
 *   status:string,
 *   rows:list<DetailGroupRow>
 * }
 */
class StatusDetailGroupsBuilder {

	use PluginControllerConsumer;
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
	 * @param list<ConfigureComponentRow> $components
	 * @return list<DetailGroup>
	 */
	public function buildForConfigure( array $components ) :array {
		$rows = [];

		foreach ( \array_values( $components ) as $index => $component ) {
			$rows[] = $this->buildConfigureComponentRow( $component, $index );
		}

		return $this->groupRows( $rows );
	}

	/**
	 * @param MaintenanceIssueItem $item
	 * @return DetailGroupRow
	 */
	private function buildMaintenanceIssueRow( array $item, int $sortIndex ) :array {
		$status = $this->normalizeStatus( $item[ 'severity' ] );

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
			'action'            => isset( $item[ 'cta' ] ) ? $this->normalizeAction( $item[ 'cta' ], '' ) : [],
			'sort_index'        => $sortIndex,
		];
	}

	/**
	 * @param AssessmentRow $row
	 * @return DetailGroupRow
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
			'sort_index'        => $sortIndex,
		];
	}

	/**
	 * @param ConfigureComponentRow $component
	 * @return DetailGroupRow
	 */
	private function buildConfigureComponentRow( array $component, int $sortIndex ) :array {
		$status = $this->normalizeStatus( $component[ 'status' ] );
		$explanations = \array_values( \array_filter(
			\array_map(
				static fn( $explanation ) :string => \trim( (string)$explanation ),
				$component[ 'explanations' ]
			),
			static fn( string $explanation ) :bool => $explanation !== ''
		) );

		return [
			'key'               => $component[ 'title' ] !== '' ? $component[ 'title' ] : 'component-'.$sortIndex,
			'title'             => $component[ 'title' ],
			'summary'           => $component[ 'note' ],
			'status'            => $status,
			'status_label'      => $component[ 'status_label' ],
			'status_icon_class' => $component[ 'status_icon_class' ],
			'count_badge'       => null,
			'badge_status'      => $this->badgeStatus( $status ),
			'explanations'      => $explanations,
			'action'            => $this->normalizeAction( $component[ 'config_action' ], __( 'Configure', 'wp-simple-firewall' ) ),
			'sort_index'        => $sortIndex,
		];
	}

	/**
	 * @param list<DetailGroupRow> $rows
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
			unset( $row[ 'sort_index' ] );
			$status = $row[ 'status' ];
			if ( empty( $groups ) || $groups[ \count( $groups ) - 1 ][ 'status' ] !== $status ) {
				$groups[] = [
					'status' => $status,
					'rows'   => [],
				];
			}
			$groups[ \count( $groups ) - 1 ][ 'rows' ][] = $row;
		}

		return $groups;
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
			'data'    => $data,
		];
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
