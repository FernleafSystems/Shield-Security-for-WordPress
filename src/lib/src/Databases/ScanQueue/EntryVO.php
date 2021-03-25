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
	 * @inheritDoc
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'items':
			case 'results':
				if ( is_string( $value ) && !empty( $value ) ) {
					$value = base64_decode( $value );
					if ( !empty( $value ) ) {
						$value = @json_decode( $value, true );
					}
				}

				if ( !is_array( $value ) ) {
					$value = [];
				}
				break;

			default:
				break;
		}
		return $value;
	}

	/**
	 * @inheritDoc
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

		parent::__set( $key, $value );
	}
}