<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops\ArrangeDiffByZone;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\SnapshotVO;

class BaseZoneReport {

	private $zoneSlug;

	public function __construct( string $zoneSlug ) {
		$this->zoneSlug = $zoneSlug;
	}

	public function buildZoneReportData( SnapshotVO $diff ) :array {
		$zoneDiff = ArrangeDiffByZone::run( $diff->data )[ $this->zoneSlug ] ?? [];
		return $this->processDiffForDisplay( $zoneDiff );
	}

	public function getZoneDescription() :array {
		return [
			'TODO: Zone Description'
		];
	}

	public function processDiffForDisplay( array $diff ) :array {
		$items = [];
		foreach ( Constants::DIFF_TYPES as $diffType ) {

			foreach ( $diff[ $diffType ] ?? [] as $uniq => $item ) {
				if ( !isset( $items[ $uniq ] ) ) {
					$items[ $uniq ] = [
						'uniq' => $uniq,
						'rows' => [],
					];
				}

				switch ( $diffType ) {
					case Constants::DIFF_TYPE_ADDED:
						if ( empty( $items[ $uniq ][ 'name' ] ) ) {
							$items[ $uniq ][ 'name' ] = $this->getItemName( $item );
							$items[ $uniq ][ 'link' ] = $this->getItemLink( $item );
						}
						$rows = $this->processDiffAdded( $item );
						break;

					case Constants::DIFF_TYPE_REMOVED:
						if ( empty( $items[ $uniq ][ 'name' ] ) ) {
							$items[ $uniq ][ 'name' ] = $this->getItemName( $item );
							$items[ $uniq ][ 'link' ] = $this->getItemLink( $item );
						}
						$rows = $this->processDiffRemoved( $item );
						break;

					case Constants::DIFF_TYPE_CHANGED:
						if ( empty( $items[ $uniq ][ 'name' ] ) ) {
							$items[ $uniq ][ 'name' ] = $this->getItemName( $item[ 'old' ] );
							$items[ $uniq ][ 'link' ] = $this->getItemLink( $item[ 'old' ] );
						}
						$rows = $this->processDiffChanged( $item[ 'old' ], $item[ 'new' ] );
						break;

					default:
						$rows = [];
						break;
				}
				$items[ $uniq ][ 'rows' ] = \array_merge( $items[ $uniq ][ 'rows' ], $rows );
			}
		}
		return $items;
	}

	protected function processDiffAdded( array $item ) :array {
		return [];
	}

	protected function processDiffRemoved( array $item ) :array {
		return [];
	}

	protected function processDiffChanged( array $old, array $new ) :array {
		return [];
	}

	protected function getItemName( array $item ) :string {
		return 'Unknown Item';
	}

	protected function getItemLink( array $item ) :array {
		return [
			'href' => '#',
			'text' => 'Unknown Href',
		];
	}

	public function getZoneName() :string {
		return \ucfirst( $this->zoneSlug );
	}
}