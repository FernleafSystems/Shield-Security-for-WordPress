<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Common;

use FernleafSystems\Wordpress\Services\Services;

class AlignTableWithSchema {

	/**
	 * @var TableSchema
	 */
	private $schema;

	/**
	 * @var string[]
	 */
	private $cols = null;

	public function __construct( TableSchema $schema ) {
		$this->schema = $schema;
	}

	public function isAligned() :bool {
		$colsActual = $this->getColumnsActual();
		$colsSchema = $this->schema->getColumnNames();
		asort( $colsActual );
		asort( $colsSchema );
		return $colsActual === $colsSchema;
	}

	public function align() {
		$DB = Services::WpDb();
		if ( !$DB->getIfTableExists( $this->schema->table ) ) {
			$DB->doSql( $this->schema->buildCreate() );
			$DB->clearResultShowTables();
		}
		else {
			$this->alignColumns();
		}
	}

	private function alignColumns() {
		$DB = Services::WpDb();
		$colsUpdated = false;

		$colsActual = $this->getColumnsActual();
		$colsSchema = $this->schema->getColumnNames();

		// Are columns missing?
		foreach ( \array_diff( $colsSchema, $colsActual ) as $col ) {
			$this->addColumn( $col );
			$colsUpdated = true;
		}

		// Extra columns?
		foreach ( \array_diff( $colsActual, $colsSchema ) as $col ) {
			$DB->doSql( sprintf( 'ALTER TABLE `%s` DROP `%s`;', $this->schema->table, $col ) );
			$colsUpdated = true;
		}

		if ( $colsUpdated ) {
			$this->cols = null;
		}
	}

	private function addColumn( string $col ) {
		$colsSchema = $this->schema->enumerateColumns();
		if ( \array_key_exists( $col, $colsSchema ) ) {

			if ( \key( $colsSchema ) === $col ) {
				$position = 'FIRST';
			}
			else {
				// find the correct position to insert the col
				while ( \key( $colsSchema ) !== $col ) {
					\next( $colsSchema );
				}
				\prev( $colsSchema );
				$position = 'AFTER '.\key( $colsSchema );
			}

			Services::WpDb()->doSql( sprintf( 'ALTER TABLE `%s` ADD COLUMN %s %s %s;',
				$this->schema->table,
				$col,
				$colsSchema[ $col ],
				$position
			) );
		}
	}

	/**
	 * @return string[]
	 */
	private function getColumnsActual() :array {
		if ( is_null( $this->cols ) ) {
			$this->cols = Services::WpDb()->getColumnsForTable( $this->schema->table );
		}
		return \is_array( $this->cols ) ? \array_map( '\strtolower', $this->cols ) : [];
	}
}