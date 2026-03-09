<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\StatusPriority;

class StatusDetailGroupsBuilder {

	use PluginControllerConsumer;
	use StandardStatusMapping;

	/**
	 * @param list<array<string,mixed>> $issueItems
	 * @param list<array<string,mixed>> $assessmentRows
	 * @return list<array{status:string,rows:list<array<string,mixed>>}>
	 */
	public function buildForMaintenance( array $issueItems, array $assessmentRows ) :array {
		$rows = [];
		$index = 0;

		foreach ( $issueItems as $item ) {
			$rows[] = $this->buildMaintenanceIssueRow( $item, $index++ );
		}

		foreach ( $assessmentRows as $row ) {
			if ( ( $row[ 'status' ] ?? '' ) !== 'good' ) {
				continue;
			}
			$rows[] = $this->buildAssessmentRow( $row, $index++ );
		}

		return $this->groupRows( $rows );
	}

	/**
	 * @param list<array<string,mixed>> $components
	 * @return list<array{status:string,rows:list<array<string,mixed>>}>
	 */
	public function buildForConfigure( array $components ) :array {
		$rows = [];

		foreach ( \array_values( $components ) as $index => $component ) {
			$rows[] = $this->buildConfigureComponentRow( $component, $index );
		}

		return $this->groupRows( $rows );
	}

	/**
	 * @param array<string,mixed> $item
	 * @return array<string,mixed>
	 */
	private function buildMaintenanceIssueRow( array $item, int $sortIndex ) :array {
		$status = $this->normalizeStatus( (string)( $item[ 'severity' ] ?? 'warning' ) );

		return [
			'key'               => (string)( $item[ 'key' ] ?? '' ),
			'title'             => (string)( $item[ 'label' ] ?? '' ),
			'summary'           => (string)( $item[ 'description' ] ?? '' ),
			'status'            => $status,
			'status_label'      => $this->standardStatusLabel( $status ),
			'status_icon_class' => $this->standardStatusIconClass( $status ),
			'count_badge'       => \max( 0, (int)( $item[ 'count' ] ?? 0 ) ),
			'badge_status'      => $this->badgeStatus( $status ),
			'explanations'      => [],
			'action'            => $this->normalizeAction( $item[ 'cta' ] ?? [], '' ),
			'sort_index'        => $sortIndex,
		];
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private function buildAssessmentRow( array $row, int $sortIndex ) :array {
		$status = $this->normalizeStatus( (string)( $row[ 'status' ] ?? 'good' ) );

		return [
			'key'               => (string)( $row[ 'key' ] ?? '' ),
			'title'             => (string)( $row[ 'label' ] ?? '' ),
			'summary'           => (string)( $row[ 'description' ] ?? '' ),
			'status'            => $status,
			'status_label'      => (string)( $row[ 'status_label' ] ?? $this->standardStatusLabel( $status ) ),
			'status_icon_class' => (string)( $row[ 'status_icon_class' ] ?? $this->standardStatusIconClass( $status ) ),
			'count_badge'       => null,
			'badge_status'      => $this->badgeStatus( $status ),
			'explanations'      => [],
			'action'            => [],
			'sort_index'        => $sortIndex,
		];
	}

	/**
	 * @param array<string,mixed> $component
	 * @return array<string,mixed>
	 */
	private function buildConfigureComponentRow( array $component, int $sortIndex ) :array {
		$status = $this->normalizeStatus( (string)( $component[ 'status' ] ?? 'good' ) );
		$explanations = \array_values( \array_filter(
			\array_map(
				static fn( $explanation ) :string => \trim( (string)$explanation ),
				\is_array( $component[ 'explanations' ] ?? null ) ? $component[ 'explanations' ] : []
			),
			static fn( string $explanation ) :bool => $explanation !== ''
		) );

		return [
			'key'               => (string)( $component[ 'title' ] ?? 'component-'.$sortIndex ),
			'title'             => (string)( $component[ 'title' ] ?? '' ),
			'summary'           => (string)( $component[ 'note' ] ?? '' ),
			'status'            => $status,
			'status_label'      => (string)( $component[ 'status_label' ] ?? $this->standardStatusLabel( $status ) ),
			'status_icon_class' => (string)( $component[ 'status_icon_class' ] ?? $this->standardStatusIconClass( $status ) ),
			'count_badge'       => null,
			'badge_status'      => $this->badgeStatus( $status ),
			'explanations'      => $explanations,
			'action'            => $this->normalizeAction( $component[ 'config_action' ] ?? [], __( 'Configure', 'wp-simple-firewall' ) ),
			'sort_index'        => $sortIndex,
		];
	}

	/**
	 * @param list<array<string,mixed>> $rows
	 * @return list<array{status:string,rows:list<array<string,mixed>>}>
	 */
	private function groupRows( array $rows ) :array {
		\usort( $rows, function ( array $a, array $b ) :int {
			$rankCmp = $this->detailStatusRank( (string)( $b[ 'status' ] ?? '' ) )
				<=> $this->detailStatusRank( (string)( $a[ 'status' ] ?? '' ) );
			if ( $rankCmp !== 0 ) {
				return $rankCmp;
			}

			return (int)( $a[ 'sort_index' ] ?? 0 ) <=> (int)( $b[ 'sort_index' ] ?? 0 );
		} );

		$groups = [];
		foreach ( $rows as $row ) {
			unset( $row[ 'sort_index' ] );
			$status = (string)( $row[ 'status' ] ?? 'good' );
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
	 * @param array<string,mixed>|mixed $action
	 * @return array<string,mixed>
	 */
	private function normalizeAction( $action, string $defaultLabel ) :array {
		if ( !\is_array( $action ) || empty( $action ) ) {
			return [];
		}

		$data = \is_array( $action[ 'data' ] ?? null ) ? $action[ 'data' ] : [];
		$classes = \is_array( $action[ 'classes' ] ?? null ) ? \array_values( \array_filter(
			\array_map( static fn( $class ) :string => \trim( (string)$class ), $action[ 'classes' ] ),
			static fn( string $class ) :bool => $class !== ''
		) ) : [];

		return [
			'label'   => (string)( $action[ 'label' ] ?? $defaultLabel ),
			'href'    => (string)( $action[ 'href' ] ?? 'javascript:{}' ),
			'title'   => (string)( $action[ 'title' ] ?? '' ),
			'target'  => (string)( $action[ 'target' ] ?? '' ),
			'icon'    => (string)( $action[ 'icon' ] ?? '' ),
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
