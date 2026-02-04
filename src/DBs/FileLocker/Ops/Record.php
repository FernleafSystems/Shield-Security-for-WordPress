<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\FileLocker\Ops;

/**
 * @property string $type
 * @property string $path
 * @property string $hash_original
 * @property string $hash_current
 * @property string $content
 * @property string $cipher
 * @property int    $public_key_id
 * @property int    $detected_at
 * @property int    $reverted_at
 * @property int    $notified_at
 * @property int    $updated_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'content':
			case 'path':
				$value = (string)\base64_decode( $value );
				break;
			case 'cipher':
			case 'type':
			case 'hash_current':
			case 'hash_original':
				$value = (string)$value;
				break;
			default:
				break;
		}
		return $value;
	}

	public function __set( string $key, $value ) {
		switch ( $key ) {
			case 'content':
			case 'path':
				$value = \base64_encode( $value );
				break;
			default:
				break;
		}
		parent::__set( $key, $value );
	}
}