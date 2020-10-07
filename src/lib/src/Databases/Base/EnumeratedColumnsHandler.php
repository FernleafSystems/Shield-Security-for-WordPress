<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class EnumeratedColumnsHandler
 * @package FernleafSystems\Wordpress\Plugin\Shield\Databases\Base
 */
abstract class EnumeratedColumnsHandler extends Handler {

	/**
	 * @return string[]
	 */
	public function enumerateColumns() :array {
		return array_merge(
			$this->getColumn_ID(),
			$this->getColumnsAsArray(),
			$this->getColumns_Ats()
		);
	}

	/**
	 * @return string[]
	 */
	public function getColumns() :array {
		return array_keys( $this->getColumnsDefinition() );
	}

	/**
	 * @return string[]
	 */
	abstract protected function getColumnsAsArray() :array;

	/**
	 * @return string[]
	 */
	public function getColumnsDefinition() :array {
		return $this->enumerateColumns();
	}

	/**
	 * @return string[]
	 */
	protected function getColumn_ID() :array {
		return [
			'id' => 'INT(11) UNSIGNED NOT NULL AUTO_INCREMENT',
		];
	}

	/**
	 * @return string[]
	 */
	protected function getTimestampColumnNames() :array {
		return [];
	}

	/**
	 * @return string[]
	 */
	protected function getColumns_Ats() :array {
		return array_map(
			function ( $comment ) {
				return $this->getTimestampColDef( $comment );
			},
			array_merge(
				$this->getTimestampColumnNames(),
				[
					'created_at' => 'Created At',
					'deleted_at' => 'Soft Deleted At',
				]
			)
		);
	}

	protected function getTimestampColDef( string $comment = '' ) {
		return sprintf( "INT(15) UNSIGNED NOT NULL DEFAULT 0 COMMENT '%s'", str_replace( "'", '', $comment ) );
	}

	protected function getDefaultCreateTableSql() :string {
		$cols = [];
		foreach ( $this->enumerateColumns() as $col => $def ) {
			$cols[] = sprintf( '%s %s', $col, $def );
		}
		$cols[] = $this->getPrimaryKeySpec();

		return "CREATE TABLE %s (
			".implode( ", ", $cols )."
		) %s;";
	}

	protected function getPrimaryKeySpec() :string {
		return 'PRIMARY KEY  (id)';
	}
}