<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

abstract class Base {

	use ModConsumer;

	abstract protected function getColumnDefs() :array;

	abstract protected function getColumnsToDisplay() :array;

	abstract protected function getOrderColumnSlug() :string;

	public function build() :string {
		return json_encode( [
			// array_values() to ensure data of the correct format
			'columns' => array_values( $this->getColumnsForDisplay() ),
			'order'   => $this->getInitialOrdering()
		] );
	}

	/**
	 * @throws \Exception
	 */
	public function getInitialOrdering() :array {
		$thePosition = 0;
		foreach ( $this->getColumnsToDisplay() as $position => $columnDef ) {
			if ( $columnDef === $this->getOrderColumnSlug() ) {
				$thePosition = $position;
				break;
			}
		}
		return [
			[ $thePosition, $this->getOrderMethod() ]
		];
	}

	protected function getOrderMethod() :string {
		return 'desc';
	}

	/**
	 * @throws \Exception
	 */
	public function getColumnsForDisplay() :array {
		$columns = [];
		foreach ( $this->getColumnsToDisplay() as $colSlug ) {
			$columns[ $colSlug ] = $this->pluckColumn( $colSlug );
		}
		return $columns;
	}

	/**
	 * @throws \Exception
	 */
	protected function pluckColumn( string $columnSlug ) :array {
		$col = null;
		foreach ( $this->getColumnDefs() as $slug => $columnDef ) {
			if ( $slug === $columnSlug ) {
				$col = $columnDef;
				break;
			}
		}
		if ( empty( $col ) ) {
			throw new \Exception( 'Column Definition does not exist for slug: '.$columnSlug );
		}
		return $col;
	}
}