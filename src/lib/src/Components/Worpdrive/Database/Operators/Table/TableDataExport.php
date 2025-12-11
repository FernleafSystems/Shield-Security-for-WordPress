<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\Config;
use FernleafSystems\Wordpress\Services\Services;

class TableDataExport {

	private Config $cfg;

	private string $table;

	private array $content = [];

	private ?int $totalDataRows = null;

	private ?int $previousDataRows = null;

	private ?array $mostRecentRow = null;

	public function __construct( string $table, Config $cfg ) {
		$this->cfg = $cfg;
		$this->table = $table;
	}

	public function getContent( bool $flush = false ) :array {
		$content = $this->content;
		if ( $flush ) {
			$this->content = [];
		}
		return $content;
	}

	public function getTotalDataRowsCount() :?int {
		return $this->totalDataRows;
	}

	public function getPreviousDataRowsCount() :?int {
		return $this->previousDataRows;
	}

	public function getMostRecentRow() :?array {
		return $this->mostRecentRow;
	}

	/**
	 * @throws \Exception
	 */
	public function buildDataRows( array $where = [], string $orderBy = '', int $limit = 0, int $offset = 0 ) :void {
		$DB = Services::WpDb();

//		error_log( sprintf(
//			"SELECT * FROM `%s` %s %s %s;",
//			$this->table,
//			empty( $where ) ? '' : sprintf( ' WHERE %s', \implode( ' AND ', $where ) ),
//			$orderBy,
//			empty( $limit ) ? '' : sprintf( ' LIMIT %s OFFSET %s', $limit, $offset )
//		) );
		$rows = $DB->selectCustom( sprintf(
			"SELECT * FROM `%s` %s %s %s;",
			$this->table,
			empty( $where ) ? '' : sprintf( ' WHERE %s', \implode( ' AND ', $where ) ),
			$orderBy,
			empty( $limit ) ? '' : sprintf( ' LIMIT %s OFFSET %s', $limit, $offset )
		) );

		$this->previousDataRows = \is_array( $rows ) ? \count( $rows ) : null;
		if ( $this->previousDataRows !== null ) {
			$this->totalDataRows += $this->previousDataRows;
		}
		if ( empty( $rows ) ) {
			$this->mostRecentRow = null;
			return;
		}

		$this->mostRecentRow = \array_pop( $rows );
		$rows[] = $this->mostRecentRow;

		$tablesLocked = false; // $this->cfg->has( 'lock-tables' ) && $DB->doSql( sprintf( 'LOCK TABLES `%s` READ LOCAL', lock-tables ) );

		// Describing the table allows us to do smarter things with the values
		$columns = ( new TableHelper( $this->table ) )->showColumns();

		// Build the INSERT prefix according to configuration:
		$insertPrefix = 'INSERT';
		if ( $this->cfg->has( 'insert-ignore' ) ) {
			$insertPrefix .= ' IGNORE';
		}
		if ( $this->cfg->has( 'delayed-insert' ) ) {
			$insertPrefix .= ' DELAYED';
		}
		$insertPrefix .= sprintf( ' INTO `%s`', $this->table );
		if ( $this->cfg->has( 'complete-insert' ) ) {
			$insertPrefix .= ' (`'.\implode( '`, `', \array_keys( $columns ) ).'`)';
		}
		$insertPrefix .= ' VALUES ';

		if ( $this->cfg->has( 'extended-insert' ) ) {
			// We attempt to build the insert statement to stay until query size limit.

			$maxQuerySize = $this->cfg->get( 'max-query-size', 10000 );
			$rowsToInsert = [];
			foreach ( \array_map( fn( $row ) => $this->convertRawRowToSqlValues( $row, $columns ), $rows ) as $row ) {

				$thisLine = sprintf( "(%s)", \implode( ',', $row ) );

				if ( \strlen( $insertPrefix.\implode( ',', $rowsToInsert ) ).$thisLine > $maxQuerySize ) {
					// Don't include the current line and create the insert
					$this->addLine( sprintf( '%s%s ;', $insertPrefix, \implode( ",\n", $rowsToInsert ) ) );
					$rowsToInsert = [];
				}
				else {
					// Add the current statement to the block
					$rowsToInsert[] = $thisLine;
				}
			}

			if ( !empty( $rowsToInsert ) ) {
				$this->addLine( sprintf( '%s%s ;', $insertPrefix, \implode( ",\n", $rowsToInsert ) ) );
			}
		}
		else {
			\array_map(
				fn( $row ) => $this->addLine(
					sprintf( "%s(%s);", $insertPrefix, \implode( ',', $this->convertRawRowToSqlValues( $row, $columns ) ) )
				),
				$rows
			);
		}
		$this->addLine( '' );

		if ( $this->cfg->has( 'single-transaction' ) ) {
			if ( $DB->doSql( 'COMMIT;' ) === false ) {
				throw new \Exception( 'Failed to commit transaction' );
			}
		}

		if ( $tablesLocked && $this->cfg->has( 'lock-tables' ) ) {
			if ( $DB->doSql( 'UNLOCK TABLES;' ) === false ) {
				throw new \Exception( 'Failed to unlock tables' );
			}
		}
	}

	private function convertRawRowToSqlValues( array $row, array $columns ) :array {
		$rowValues = [];
		foreach ( $columns as $field => $col ) {
			if ( \preg_match( '#^int|bigint|mediumint|smallint|tinyint|bool|decimal|float|double|bit#i', $col[ 'Type' ] ) ) {
				$rowValues[] = \is_null( $row[ $field ] ) ? 'NULL' : $row[ $field ];
			}
			elseif ( $this->cfg->has( 'hex-blob' ) && \preg_match( '#^blob|longblob|mediumblob|tinyblob|binary|varbinary#i', $col[ 'Type' ] ) ) {
				if ( \preg_match( '#^bit#i', $col[ 'Type' ] ) ) {
					$rowValues[] = '0x'.\bin2hex( $row[ $field ] );
				}
				else {
					$rowValues[] = $row[ $field ] == '' ? "''" : '0x'.\bin2hex( $row[ $field ] );
				}
			}
			else {
				$rowValues[] = \is_null( $row[ $field ] ) ?
					'NULL' : "'".Services::WpDb()->loadWpdb()->_real_escape( $row[ $field ] )."'";
			}
		}
		return $rowValues;
	}

	public function addLine( string $line ) {
		$this->addContent( [ $line ] );
	}

	public function addContent( array $lines ) {
		$this->content = \array_merge( $this->content, $lines );
	}
}