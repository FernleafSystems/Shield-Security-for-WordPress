<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;

abstract class Base {

	use PluginControllerConsumer;

	abstract protected function getColumnDefs() :array;

	abstract protected function getColumnsToDisplay() :array;

	abstract protected function getOrderColumnSlug() :string;

	public function build() :string {
		return \wp_json_encode( $this->buildRaw() );
	}

	public function buildRaw() :array {
		return [
			// \array_values() to ensure data of the correct format
			'columns'     => \array_values( $this->getColumnsForDisplay() ),
			'order'       => $this->getInitialOrdering(),
			'searchPanes' => $this->getSearchPanesData()
		];
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

	protected function getSearchPanesData() :array {
		return [
			'cascadePanes'  => false,
			'viewTotal'     => false,
			'viewCount'     => false,
			'initCollapsed' => true,
			'i18n'          => [
				'clearMessage' => __( 'Clear All Filters', 'wp-simple-firewall' ),
			]
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
			$col = $this->pluckColumn( $colSlug );

			if ( $col[ 'search_builder' ] ?? false ) {
				$col[ 'className' ] .= ' search_builder';
			}

			$columns[ $colSlug ] = $col;
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