<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class ShieldNetApiDataVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi
 * @property int   $last_handshake_at
 * @property int   $last_handshake_attempt_at
 * @property int[] $nonces
 */
class ShieldNetApiDataVO {

	use StdClassAdapter {
		__get as __adapterGet;
	}

	/**
	 * @param string $sProperty
	 * @return mixed
	 */
	public function __get( $sProperty ) {

		$mValue = $this->__adapterGet( $sProperty );

		switch ( $sProperty ) {

			case 'nonces':
				if ( !is_array( $mValue ) ) {
					$mValue = [];
				}
				break;

			default:
				break;
		}

		return $mValue;
	}
}
