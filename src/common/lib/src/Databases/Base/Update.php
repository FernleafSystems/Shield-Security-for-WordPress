<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Wordpress\Services\Services;

class Update extends Insert {

	/**
	 * @var array
	 */
	protected $aUpdateWheres;

	/**
	 * @return array
	 */
	public function getUpdateData() {
		return $this->getInsertData();
	}

	/**
	 * @return array
	 */
	public function getUpdateWheres() {
		return is_array( $this->aUpdateWheres ) ? $this->aUpdateWheres : array();
	}

	/**
	 * @param array $aSetData
	 * @return $this
	 */
	public function setUpdateData( $aSetData ) {
		return $this->setInsertData( $aSetData );
	}

	/**
	 * @param array $aUpdateWheres
	 * @return $this
	 */
	public function setUpdateWheres( $aUpdateWheres ) {
		$this->aUpdateWheres = $aUpdateWheres;
		return $this;
	}

	/**
	 * @param int $nId
	 * @return $this
	 */
	public function setUpdateId( $nId ) {
		$this->aUpdateWheres = array( 'id' => $nId );
		return $this;
	}

	/**
	 * @param EntryVO $oEntry
	 * @param array   $aUpdateData
	 * @return bool
	 */
	public function updateEntry( $oEntry, $aUpdateData = array() ) {
		$bSuccess = false;

		if ( $oEntry instanceof EntryVO ) {
			$bSuccess = $this->updateById( $oEntry->id, $aUpdateData );
			// TODO: run through update data and determine if anything actually needs updating
			if ( $bSuccess ) {
				foreach ( $aUpdateData as $sCol => $mVal ) {
					$oEntry->{$sCol} = $mVal;
				}
			}
		}
		return $bSuccess;
	}

	/**
	 * @param int   $nId
	 * @param array $aUpdateData
	 * @return bool true is success or no update necessary
	 */
	public function updateById( $nId, $aUpdateData = array() ) {
		$bSuccess = true;

		if ( !empty( $aUpdateData ) ) {
			$mResult = $this
				->setUpdateId( $nId )
				->setUpdateData( $aUpdateData )
				->query();
			$bSuccess = $mResult === 1;
		}
		return $bSuccess;
	}

	/**
	 * @return int|false
	 */
	public function query() {
		return Services::WpDb()
					   ->updateRowsFromTableWhere(
						   $this->getDbH()->getTable(),
						   $this->getUpdateData(),
						   $this->getUpdateWheres()
					   );
	}
}