<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Mfa\Ops;

/**
 * @property int    $user_id
 * @property string $slug
 * @property string $unique_id
 * @property string $label
 * @property array  $data
 * @property bool   $passwordless
 * @property int    $used_at
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
			case 'label':
			case 'unique_id':
			case 'slug':
				$value = (string)$value;
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