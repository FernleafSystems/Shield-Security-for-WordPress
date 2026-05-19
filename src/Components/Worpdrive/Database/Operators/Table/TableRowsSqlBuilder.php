<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\{
	Config,
	SqlIdentifier
};
use FernleafSystems\Wordpress\Services\Services;

class TableRowsSqlBuilder {

	private Config $cfg;

	public function __construct( Config $cfg ) {
		$this->cfg = $cfg;
	}

	/**
	 * @throws \Exception
	 */
	public function buildInsertLines( string $table, array $rows, array $columns ) :array {
		if ( empty( $rows ) ) {
			return [];
		}
		if ( empty( $columns ) ) {
			throw new \Exception( 'Cannot export rows without column metadata.' );
		}

		$insertPrefix = $this->buildInsertPrefix( $table, $columns );
		$rowValues = \array_map(
			fn( array $row ) => \sprintf( '(%s)', \implode( ',', $this->convertRawRowToSqlValues( $row, $columns ) ) ),
			$rows
		);

		$lines = [];
		if ( $this->cfg->has( 'extended-insert' ) ) {
			$maxQuerySize = (int)$this->cfg->get( 'max-query-size', 10000 );
			$rowsToInsert = [];
			foreach ( $rowValues as $rowValue ) {
				$nextRows = \array_merge( $rowsToInsert, [ $rowValue ] );
				if ( !empty( $rowsToInsert )
					 && \strlen( $insertPrefix.\implode( ",\n", $nextRows ).';' ) > $maxQuerySize ) {
					$lines[] = \sprintf( '%s%s;', $insertPrefix, \implode( ",\n", $rowsToInsert ) );
					$rowsToInsert = [ $rowValue ];
				}
				else {
					$rowsToInsert = $nextRows;
				}
			}
			if ( !empty( $rowsToInsert ) ) {
				$lines[] = \sprintf( '%s%s;', $insertPrefix, \implode( ",\n", $rowsToInsert ) );
			}
		}
		else {
			foreach ( $rowValues as $rowValue ) {
				$lines[] = \sprintf( '%s%s;', $insertPrefix, $rowValue );
			}
		}

		$lines[] = '';
		return $lines;
	}

	/**
	 * @throws \Exception
	 */
	public function convertRawRowToSqlValues( array $row, array $columns ) :array {
		$rowValues = [];
		foreach ( $columns as $field => $column ) {
			SqlIdentifier::assertSafe( $field, 'Column name' );
			if ( !\array_key_exists( $field, $row ) ) {
				throw new \Exception( \sprintf( 'Missing column value for export field: %s', $field ) );
			}

			$value = $row[ $field ];
			$type = $this->columnType( $column );
			if ( $value === null ) {
				$rowValues[] = 'NULL';
			}
			elseif ( $this->isBinaryType( $type ) && $this->cfg->has( 'hex-blob' ) ) {
				$rowValues[] = $value === '' ? "''" : '0x'.\bin2hex( (string)$value );
			}
			elseif ( $this->isNumericType( $type ) ) {
				$rowValues[] = (string)$value;
			}
			else {
				$rowValues[] = "'".Services::WpDb()->loadWpdb()->_real_escape( (string)$value )."'";
			}
		}
		return $rowValues;
	}

	private function buildInsertPrefix( string $table, array $columns ) :string {
		$insertPrefix = $this->cfg->has( 'replace' ) ? 'REPLACE' : 'INSERT';
		if ( $this->cfg->has( 'insert-ignore' ) ) {
			$insertPrefix .= ' IGNORE';
		}
		if ( $this->cfg->has( 'delayed-insert' ) ) {
			$insertPrefix .= ' DELAYED';
		}
		$insertPrefix .= \sprintf( ' INTO %s', SqlIdentifier::quote( $table, 'Table name' ) );
		if ( $this->cfg->has( 'complete-insert' ) ) {
			$insertPrefix .= ' ('.\implode(
				', ',
				\array_map(
					fn( string $column ) => SqlIdentifier::quote( $column, 'Column name' ),
					\array_keys( $columns )
				)
			).')';
		}
		return $insertPrefix.' VALUES ';
	}

	private function columnType( array $column ) :string {
		return \strtolower( (string)( $column[ 'Type' ] ?? '' ) );
	}

	private function isBinaryType( string $type ) :bool {
		return \preg_match( '#^(bit|blob|longblob|mediumblob|tinyblob|binary|varbinary)#i', $type ) === 1;
	}

	private function isNumericType( string $type ) :bool {
		return \preg_match( '#^(int|bigint|mediumint|smallint|tinyint|bool|decimal|float|double)#i', $type ) === 1;
	}
}
