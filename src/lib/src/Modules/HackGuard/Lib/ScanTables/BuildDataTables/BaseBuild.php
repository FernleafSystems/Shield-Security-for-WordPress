<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\BuildDataTables;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

class BaseBuild {

	use ModConsumer;

	public function build() :string {
		return json_encode( [
			'columns' => $this->getColumnsForDisplay(),
			'order'   => $this->getInitialOrdering()
		] );
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function getInitialOrdering() :array {
		$thePosition = 0;
		foreach ( $this->getColumnsForDisplay() as $position => $columnDef ) {
			if ( $columnDef === $this->getOrderColumnSlug() ) {
				$thePosition = $position;
				break;
			}
		}
		return [
			[ $thePosition, $this->getOrderMethod() ]
		];
	}

	protected function getOrderColumnSlug() :string {
		return 'detected';
	}

	protected function getOrderMethod() :string {
		return 'desc';
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function getColumnsForDisplay() :array {
		$columns = [];
		foreach ( $this->getColumnsToDisplay() as $colSlug ) {
			$columns[] = $this->pluckColumn( $colSlug );
		}
		return $columns;
	}

	protected function getColumnsToDisplay() :array {
		return [
			'rid',
			'file',
		];
	}

	/**
	 * @param string $columnSlug
	 * @return array
	 * @throws \Exception
	 */
	protected function pluckColumn( string $columnSlug ) :array {
		$col = null;
		foreach ( $this->getColumnDefs() as $columnDef ) {
			if ( $columnDef[ 'slug' ] === $columnSlug ) {
				$col = $columnDef;
				break;
			}
		}
		if ( empty( $col ) ) {
			throw new \Exception( 'Column Definition does not exist for slug: '.$columnSlug );
		}
		return $col;
	}

	protected function getColumnDefs() :array {
		return [
			[
				'slug'       => 'rid',
				'data'       => 'rid',
				'title'      => 'ID',
				'orderable'  => true,
				'searchable' => false,
				'visible'    => false,
			],
			[
				'slug'       => 'file',
				'data'       => 'file',
				'title'      => __( 'File' ),
				'className'  => 'file',
				'orderable'  => true,
				'searchable' => true,
				'visible'    => true,
			],
			[
				'slug'       => 'file_as_href',
				'data'       => 'file_as_href',
				'title'      => __( 'File' ),
				'className'  => 'file_as_href',
				'orderable'  => true,
				'searchable' => true,
				'visible'    => true,
			],
			[
				'slug'       => 'file_type',
				'data'       => 'file_type',
				'title'      => __( 'Type' ),
				'className'  => 'file_type',
				'orderable'  => true,
				'searchable' => true,
				'visible'    => true,
			],
			[
				'slug'       => 'status',
				'data'       => 'status',
				'title'      => __( 'Status' ),
				'className'  => 'status',
				'orderable'  => true,
				'searchable' => false,
				'visible'    => true,
			],
			[
				'slug'       => 'detected',
				'data'       => [
					'_'    => 'detected_since',
					'sort' => 'detected_at',
				],
				'title'      => __( 'Detected' ),
				'className'  => 'detected',
				'orderable'  => true,
				'searchable' => false,
				'visible'    => true,
			],
			[
				'slug'       => 'actions',
				'data'       => 'actions',
				'title'      => __( 'Actions' ),
				'className'  => 'actions',
				'orderable'  => false,
				'searchable' => false,
				'visible'    => true,
			],
			[
				'slug'       => 'fp_confidence',
				'data'       => 'fp_confidence',
				'title'      => __( 'False Positive Confidence' ),
				'className'  => 'fp_confidence',
				'orderable'  => true,
				'searchable' => false,
				'visible'    => true,
			],
		];
	}
}