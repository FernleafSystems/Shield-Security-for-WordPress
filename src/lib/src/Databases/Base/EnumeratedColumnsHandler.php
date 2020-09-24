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
	public function enumerateColumns() {
		return array_merge(
			$this->getColumn_ID(),
			$this->getColumnsAsArray(),
			$this->getColumns_Ats()
		);
	}

	/**
	 * @return string[]
	 */
	public function getColumns() {
		return array_keys( $this->getColumnsDefinition() );
	}

	/**
	 * @return string[]
	 */
	abstract protected function getColumnsAsArray();

	/**
	 * @return string[]
	 */
	public function getColumnsDefinition() {
		return $this->enumerateColumns();
	}

	/**
	 * @return string[]
	 */
	protected function getColumn_ID() {
		return [
			'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
		];
	}

	/**
	 * @return string[]
	 */
	protected function getColumns_Ats() {
		return [
			'created_at' => "int(15) UNSIGNED NOT NULL DEFAULT 0",
			'deleted_at' => "int(15) UNSIGNED NOT NULL DEFAULT 0",
		];
	}

	/**
	 * @return string
	 */
	protected function getDefaultCreateTableSql() {
		$aCols = [];
		foreach ( $this->enumerateColumns() as $col => $def ) {
			$aCols[] = sprintf( '%s %s', $col, $def );
		}
		$aCols[] = $this->getPrimaryKeySpec();

		return "CREATE TABLE %s (
			".implode( ", ", $aCols )."
		) %s;";
	}

	/**
	 * @return string
	 */
	protected function getPrimaryKeySpec() {
		return 'PRIMARY KEY  (id)';
	}
}