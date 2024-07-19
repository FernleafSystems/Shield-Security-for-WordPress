<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\Ops;

/**
 * @property string $ip
 * @property array  $geo
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'geo':
				if ( \is_string( $value ) && !empty( $value ) ) {
					$value = \base64_decode( $value );
					if ( !empty( $value ) ) {
						$value = @\json_decode( $value, true );
					}
				}

				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;

			default:
				break;
		}

		return $value;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function __set( string $key, $value ) {

		switch ( $key ) {

			case 'geo':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				$value = \base64_encode( \wp_json_encode( $value ) );
				break;

			default:
				break;
		}

		parent::__set( $key, $value );
	}
}