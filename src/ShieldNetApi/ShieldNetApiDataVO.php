<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property int[] $nonces
 * @property int   $handshake_fail_count
 * @property int   $last_handshake_at
 * @property int   $last_handshake_attempt_at
 * @property int   $last_send_iprep_at
 * @property int   $data_last_saved_at
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
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;

			default:
				break;
		}

		if ( $key === 'handshake_fail_count' || \preg_match( '#_at$#', $key ) ) {
			$value = (int)$value;
		}

		return $value;
	}
}