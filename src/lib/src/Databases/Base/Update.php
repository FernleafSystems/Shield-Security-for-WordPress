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
		return \is_array( $this->aUpdateWheres ) ? $this->aUpdateWheres : [];
	}

	public function setSoftDeleted() {
		return $this->setUpdateData( [ 'deleted_at' => Services::Request()->ts() ] );
	}

	/**
	 * @param array $data
	 * @return $this
	 */
	public function setUpdateData( $data ) {
		return $this->setInsertData( $data );
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
	 * @param EntryVO $entry
	 * @param array   $updateData
	 * @return bool
	 */
	public function updateEntry( $entry, $updateData = [] ) :bool {
		$success = false;

		if ( $entry instanceof EntryVO ) {

			foreach ( $entry->getRawData() as $key => $value ) {
				if ( isset( $updateData[ $key ] ) && $updateData[ $key ] === $value ) {
					unset( $updateData[ $key ] );
				}
			}

			if ( empty( $updateData ) ) {
				$success = true;
			}
			else {
				if ( $this->getDbH()->getTableSchema()->hasColumn( 'updated_at' )
					 && !isset( $updateData[ 'updated_at' ] ) ) {
					$updateData[ 'updated_at' ] = Services::Request()->ts();
				}
				if ( $this->updateById( $entry->id, $updateData ) ) {
					$entry->applyFromArray( \array_merge( $entry->getRawData(), $updateData ) );
					$success = true;
				}
			}
		}

		return $success;
	}

	/**
	 * @param int   $id
	 * @param array $updateData
	 * @return bool true is success or no update necessary
	 */
	public function updateById( $id, $updateData = [] ) {
		$success = true;

		if ( !empty( $updateData ) ) {
			$success = $this->setUpdateId( $id )
							->setUpdateData( $updateData )
							->query();
		}
		return $success;
	}

	public function query() {
		return (bool)Services::WpDb()
							 ->updateRowsFromTableWhere(
								 $this->getDbH()->getTable(),
								 $this->getUpdateData(),
								 $this->getUpdateWheres()
							 );
	}
}