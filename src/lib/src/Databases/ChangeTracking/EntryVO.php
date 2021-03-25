<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ChangeTracking;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class EntryVO
 * @property string ip
 * @property array  data
 * @property array  meta
 */
class EntryVO extends Base\EntryVO {

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get(string $key ) {

		$mVal = parent::__get( $key );

		switch ( $key ) {

			case 'data':
				$mVal = json_decode( \WP_Http_Encoding::decompress( $mVal ), true );
				break;

			default:
				break;
		}

		return $mVal;
	}

	/**
	 * @inheritDoc
	 */
	public function __set( string $key, $value ) {

		switch ( $key ) {

			case 'data':
				$value = \WP_Http_Encoding::compress( json_encode( $value ) );
				break;

			default:
				break;
		}

		parent::__set( $key, $value );
	}
}