<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

/**
 * @property string $file
 * @property string $hash_original
 * @property string $hash_current
 * @property string $content
 * @property int    $public_key_id
 * @property int    $detected_at
 * @property int    $reverted_at
 * @property int    $notified_at
 * @property int    $updated_at
 */
class EntryVO extends Base\EntryVO {


	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {
			case 'content':
			case 'file':
				$value = (string)base64_decode( $value );
				break;

			default:
				break;
		}
		return $value;
	}

	public function __set( string $key, $value ) {
		switch ( $key ) {
			case 'content':
			case 'file':
				$value = base64_encode( $value );
				break;

			default:
				break;
		}
		parent::__set( $key, $value );
	}
}