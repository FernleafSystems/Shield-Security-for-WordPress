<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ChangeTracking;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class EntryVO
 *
 * @property string ip
 * @property array  data
 * @property array  meta
 */
class EntryVO extends Base\EntryVO {

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {

		$mVal = parent::__get( $sProperty );

		switch ( $sProperty ) {

			case 'data':
				$mVal = json_decode( \WP_Http_Encoding::decompress( $mVal ), true );
				break;

			default:
				break;
		}

		return $mVal;
	}

	/**
	 * @param string $sProperty
	 * @param mixed  $mValue
	 * @return $this|mixed
	 */
	public function __set( $sProperty, $mValue ) {

		switch ( $sProperty ) {

			case 'data':
				$mValue = \WP_Http_Encoding::compress( json_encode( $mValue ) );
				break;

			default:
				break;
		}

		return parent::__set( $sProperty, $mValue );
	}
}