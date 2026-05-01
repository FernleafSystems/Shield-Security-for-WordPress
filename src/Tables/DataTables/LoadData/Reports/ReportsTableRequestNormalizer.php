<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForReports;

class ReportsTableRequestNormalizer {

	private const DEFAULT_LENGTH = 25;
	private const MIN_LENGTH = 1;
	private const MAX_LENGTH = 100;

	/**
	 * @param array<string,mixed> $tableData
	 * @return array{
	 *     draw:int,
	 *     start:int,
	 *     length:int,
	 *     search:array{value:string},
	 *     columns:array<int,array<string,mixed>>,
	 *     order:list<array{column:int,dir:string}>
	 * }
	 */
	public function normalize( array $tableData ) :array {
		$reportsConfig = ( new ForReports() )->buildRaw();
		$columns = \is_array( $reportsConfig[ 'columns' ] ?? null ) ? $reportsConfig[ 'columns' ] : [];

		return [
			'draw'    => \max( 0, $this->normalizeInteger( $tableData[ 'draw' ] ?? 0, 0 ) ),
			'start'   => \max( 0, $this->normalizeInteger( $tableData[ 'start' ] ?? 0, 0 ) ),
			'length'  => $this->normalizeLength( $tableData[ 'length' ] ?? self::DEFAULT_LENGTH ),
			'search'  => [
				'value' => $this->normalizeSearchValue( $tableData[ 'search' ][ 'value' ] ?? '' ),
			],
			'columns' => $columns,
			'order'   => [
				[
					'column' => $this->createdAtSortColumnIndex( $columns ),
					'dir'    => $this->normalizeOrderDirection( $tableData[ 'order' ][ 0 ][ 'dir' ] ?? 'DESC' ),
				],
			],
		];
	}

	private function normalizeLength( $length ) :int {
		if ( !$this->isIntegerLikeScalar( $length ) ) {
			$length = self::DEFAULT_LENGTH;
		}
		return \min( self::MAX_LENGTH, \max( self::MIN_LENGTH, (int)$length ) );
	}

	private function normalizeInteger( $value, int $default ) :int {
		return $this->isIntegerLikeScalar( $value ) ? (int)$value : $default;
	}

	private function isIntegerLikeScalar( $value ) :bool {
		return \is_scalar( $value ) && \preg_match( '#^-?\d+$#', (string)$value ) === 1;
	}

	private function normalizeSearchValue( $value ) :string {
		return \is_scalar( $value ) ? (string)$value : '';
	}

	private function normalizeOrderDirection( $dir ) :string {
		$dir = \is_scalar( $dir ) ? \strtoupper( (string)$dir ) : 'DESC';
		return \in_array( $dir, [ 'ASC', 'DESC' ], true ) ? $dir : 'DESC';
	}

	private function createdAtSortColumnIndex( array $columns ) :int {
		foreach ( $columns as $index => $column ) {
			$data = \is_array( $column ) ? ( $column[ 'data' ] ?? null ) : null;
			if ( \is_array( $data ) && ( $data[ 'sort' ] ?? '' ) === 'created_at' ) {
				return (int)$index;
			}
		}
		return 0;
	}
}
