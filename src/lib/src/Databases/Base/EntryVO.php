<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property int   $id
 * @property array $meta
 * @property int   $created_at
 * @property int   $deleted_at
 */
class EntryVO extends DynPropertiesClass {

	public function __construct( array $row = [] ) {
		$this->applyFromArray( $row );
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {

			case 'meta':
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

		if ( $key === 'id' || preg_match( '#^.*_at$#i', $key ) ) {
			$value = (int)$value;
		}

		return $value;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function __set( string $key, $value ) {

		switch ( $key ) {

			case 'meta':
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

	public function getCreatedAt() :int {
		return (int)$this->created_at;
	}

	public function getHash() :string {
		$data = $this->getRawData();
		asort( $data );
		return md5( serialize( $data ) );
	}

	public function isDeleted() :bool {
		return $this->deleted_at > 0;
	}
}