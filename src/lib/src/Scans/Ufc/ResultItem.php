<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ufc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\FileResultItem;

/**
 * @property bool $is_unrecognised
 */
class ResultItem extends FileResultItem {

	/**
	 * @inheritDoc
	 */
	public function __get( string $key ) {
		switch ( $key ) {
			case 'is_unrecognised':
				$value = true;
				break;
			default:
				$value = parent::__get( $key );
				break;
		}
		return $value;
	}
}