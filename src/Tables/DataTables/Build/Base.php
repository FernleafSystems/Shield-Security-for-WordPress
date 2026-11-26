<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

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
				'clearMessage' => esc_html__( 'Clear All Filters', 'wp-simple-firewall' ),
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
			// DataTables prints titles as HTML. A title may hide its text visually in a span; its translation can't add more.
			$col[ 'title' ] = wp_kses( (string)( $col[ 'title' ] ?? '' ), [ 'span' => [ 'class' => true ] ] );
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
			throw new \Exception( 'Column Definition does not exist for slug: '.$columnSlug ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-configuration guard: the slug comes from the table's own column lists. Table actions return it as plain text (TableActionBase::exec()) and IpAnalyse sections escape it (SectionRenderFallback).
		}
		return $col;
	}

	public function exportColumnDefs() :array {
		return $this->getColumnDefs();
	}

	public function exportColumnsToDisplay() :array {
		return $this->getColumnsToDisplay();
	}

	public function exportOrderColumnSlug() :string {
		return $this->getOrderColumnSlug();
	}
}
