<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class BaseEntryVO
 *
 * @property int created_at
 * @property int deleted_at
 * @property int id
 */
class EntryVO {

	use StdClassAdapter;

	/**
	 * @param array $aRow
	 */
	public function __construct( $aRow = null ) {
		$this->applyFromArray( $aRow );
	}

	/**
	 * @return int
	 */
	public function getCreatedAt() {
		return (int)$this->created_at;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return (int)$this->id;
	}

	/**
	 * @return bool
	 */
	public function isDeleted() {
		return $this->deleted_at > 0;
	}
}