<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * Class EntryVO
 * @property string $rid
 * @property int    $uid
 * @property string $ip
 * @property string $path
 * @property string $code
 * @property string $ua
 * @property string $verb
 * @property bool   $trans
 */
class EntryVO extends Base\EntryVO {

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {
		switch ( $key ) {

			case 'ip':
				$mVal = inet_ntop( parent::__get( $key ) );
				break;

			default:
				$mVal = parent::__get( $key );
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

			case 'ip':
				$value = inet_pton( $value );
				break;

			default:
				break;
		}

		return parent::__set( $key, $value );
	}
}