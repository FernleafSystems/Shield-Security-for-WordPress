<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\DB\ScanItems\Ops;

/**
 * @property int   $scan_ref
 * @property array $items
 * @property int   $started_at
 * @property int   $finished_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'items':
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
	 * @param string $key
	 * @param mixed  $value
	 */
	public function __set( string $key, $value ) {

		switch ( $key ) {

			case 'items':
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