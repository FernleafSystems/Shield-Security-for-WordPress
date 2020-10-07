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
		return $this->getBuilder()->enumerateColumns();
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
		return $this->getBuilder()->enumerateColumns();
	}

	/**
	 * @return string[]
	 */
	protected function getTimestampColumnNames() :array {
		return [];
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function tableCreate() {
		$this->getBuilder()->create();
		return $this;
	}

	protected function getBuilder() :TableBuilder {
		$builder = new TableBuilder();
		$builder->table = $this->getTable();
		$builder->cols_custom = $this->getColumnsAsArray();
		$builder->cols_timestamps = $this->getTimestampColumnNames();
		return $builder;
	}
}