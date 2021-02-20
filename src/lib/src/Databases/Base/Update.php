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
		return is_array( $this->aUpdateWheres ) ? $this->aUpdateWheres : [];
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
		$this->aUpdateWheres = [ 'id' => $nId ];
		return $this;
	}

	/**
	 * @param EntryVO $entryVO
	 * @param array   $updateData
	 * @return bool
	 */
	public function updateEntry( $entryVO, $updateData = [] ) {
		$success = false;

		if ( $entryVO instanceof EntryVO ) {
			if ( empty( $updateData ) ) {
				$updateData = $entryVO->getRawData();
			}
			$success = $this->updateById( $entryVO->id, $updateData );
			// TODO: run through update data and determine if anything actually needs updating
			if ( $success ) {
				foreach ( $updateData as $col => $mVal ) {
					$entryVO->{$col} = $mVal;
				}
			}
		}
		return $success;
	}

	/**
	 * @param int   $nId
	 * @param array $aUpdateData
	 * @return bool true is success or no update necessary
	 */
	public function updateById( $nId, $aUpdateData = [] ) {
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