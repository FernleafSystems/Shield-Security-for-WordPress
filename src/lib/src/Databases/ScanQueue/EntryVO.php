<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ScanQueue;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class EntryVO
 * @property string $scan
 * @property array  $items
 * @property array  $results
 * @property int    $started_at
 * @property int    $finished_at
 */
class EntryVO extends Base\EntryVO {

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {

		$mVal = parent::__get( $sProperty );

		switch ( $sProperty ) {

			case 'items':
			case 'results':
				if ( is_string( $mVal ) && !empty( $mVal ) ) {
					$mVal = base64_decode( $mVal );
					if ( !empty( $mVal ) ) {
						$mVal = @json_decode( $mVal, true );
					}
				}

				if ( !is_array( $mVal ) ) {
					$mVal = [];
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

			case 'items':
			case 'results':
				if ( !is_array( $mValue ) ) {
					$mValue = [];
				}
				$mValue = base64_encode( json_encode( $mValue ) );
				break;

			default:
				break;
		}

		return parent::__set( $sProperty, $mValue );
	}
}