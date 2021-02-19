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
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {

		$mVal = parent::__get( $key );

		switch ( $key ) {

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
	 * @param string $key
	 * @param mixed  $value
	 * @return $this
	 */
	public function __set( string $key, $value ) {

		switch ( $key ) {

			case 'items':
			case 'results':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				$value = base64_encode( json_encode( $value ) );
				break;

			default:
				break;
		}

		return parent::__set( $key, $value );
	}
}