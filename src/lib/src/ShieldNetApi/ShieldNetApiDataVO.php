<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * Class ShieldNetApiDataVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi
 * @property int   $last_handshake_at
 * @property int   $last_handshake_attempt_at
 * @property int   $handshake_fail_count
 * @property int[] $nonces
 */
class ShieldNetApiDataVO extends DynPropertiesClass {

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'nonces':
				if ( !is_array( $value ) ) {
					$value = [];
				}
				break;

			case 'last_handshake_at':
			case 'last_handshake_attempt_at':
			case 'handshake_fail_count':
				$value = (int)$value;
				break;

			default:
				break;
		}

		return $value;
	}
}
