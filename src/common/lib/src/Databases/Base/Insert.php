<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Wordpress\Services\Services;

class Insert extends BaseQuery {

	/**
	 * @var array
	 */
	protected $aInsertData;

	/**
	 * @return array
	 */
	public function getInsertData() {
		return is_array( $this->aInsertData ) ? $this->aInsertData : array();
	}

	/**
	 * @param EntryVO $oEntry
	 * @return bool
	 */
	public function insert( $oEntry ) {
		$aData = array_merge(
			array(
				'created_at' => Services::Request()->ts(),
			),
			$oEntry->getRawData()
		);
		return $this->setInsertData( $aData )->query() === 1;
	}

	/**
	 * @param array $aData
	 * @return $this
	 */
	public function setInsertData( $aData ) {
		if ( !isset( $aData[ 'updated_at' ] ) && $this->getDbH()->hasColumn( 'updated_at' ) ) {
			$aData[ 'updated_at' ] = Services::Request()->ts();
		}
		$this->aInsertData = $aData;
		return $this;
	}

	/**
	 * @return false|int
	 */
	public function query() {
		return Services::WpDb()
					   ->insertDataIntoTable(
						   $this->getDbH()->getTable(),
						   $this->getInsertData()
					   );
	}

	/**
	 * Offset never applies
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return '';
	}
}