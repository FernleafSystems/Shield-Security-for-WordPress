<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class BaseEntryVO
 *
 * @property array meta
 * @property int   created_at
 * @property int   deleted_at
 * @property int   id
 */
class EntryVO {

	use StdClassAdapter {
		__get as __adapterGet;
		__set as __adapterSet;
	}

	/**
	 * @param array $aRow
	 */
	public function __construct( $aRow = null ) {
		$this->applyFromArray( $aRow );
	}

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {

		$mVal = $this->__adapterGet( $sProperty );

		switch ( $sProperty ) {

			case 'meta':
				if ( is_string( $mVal ) && !empty( $mVal ) ) {
					$mVal = base64_decode( $mVal );
					if ( !empty( $mVal ) ) {
						$mVal = @json_decode( $mVal, true );
					}
				}

				if ( !is_array( $mVal ) ) {
					$mVal = array();
				}
				break;

			default:
				break;
		}

		return $mVal;
	}

	/**
	 * @param string $sProperty
	 * @param mixed  $mValue
	 * @return $this
	 */
	public function __set( $sProperty, $mValue ) {

		switch ( $sProperty ) {

			case 'meta':
				if ( !is_array( $mValue ) ) {
					$mValue = array();
				}
				$mValue = base64_encode( json_encode( $mValue ) );
				break;

			default:
				break;
		}

		return $this->__adapterSet( $sProperty, $mValue );
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
	public function getMeta() {
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