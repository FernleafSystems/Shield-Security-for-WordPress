<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Snapshots\Ops;

/**
 * @property string $slug
 * @property array  $data
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'data':
				$value = @\json_decode( @\base64_decode( $value ), true );
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			default:
				break;
		}

		return $value;
	}

	public function __set( string $key, $value ) {
		switch ( $key ) {
			case 'data':
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