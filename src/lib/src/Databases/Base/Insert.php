<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Wordpress\Services\Services;

class Insert extends BaseQuery {

	/**
	 * @var array
	 */
	protected $aInsertData;

	public function getInsertData() :array {
		$dbh = $this->getDbH();
		$cols = $dbh->getTableSchema()->getColumnNames();
		return array_intersect_key(
			is_array( $this->aInsertData ) ? $this->aInsertData : [],
			array_flip( $cols )
		);
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function insert( $oEntry ) :bool {
		return $this->setInsertData( $oEntry->getRawDataAsArray() )->query() === 1;
	}

	/**
	 * Verifies insert data keys against actual table columns
	 * @param array $data
	 * @return $this
	 */
	protected function setInsertData( $data ) {
		if ( !is_array( $data ) ) {
			$data = [];
		}

		$this->aInsertData = array_intersect_key(
			$data,
			array_flip( $this->getDbH()->getTableSchema()->getColumnNames() )
		);
		return $this;
	}

	/**
	 * @return $this
	 * @throws \Exception
	 */
	protected function verifyInsertData() {
		$aData = $this->getInsertData();

		if ( !is_array( $aData ) ) {
			$aData = [];
		}
		$aData = array_merge(
			[ 'created_at' => Services::Request()->ts(), ],
			$aData
		);
		if ( !isset( $aData[ 'updated_at' ] ) && $this->getDbH()->hasColumn( 'updated_at' ) ) {
			$aData[ 'updated_at' ] = Services::Request()->ts();
		}

		return $this->setInsertData( $aData );
	}

	/**
	 * @return bool|int
	 */
	public function query() {
		try {
			$this->verifyInsertData();
			$success = Services::WpDb()
							   ->insertDataIntoTable(
								   $this->getDbH()->getTable(),
								   $this->getInsertData()
							   );
		}
		catch ( \Exception $e ) {
			$success = false;
		}
		return $success;
	}

	/**
	 * Offset never applies
	 *
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return '';
	}
}