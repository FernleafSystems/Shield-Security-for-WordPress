<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class SyncVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common
 * @property array[]    $modules
 * @property SyncMetaVO $meta
 */
class SyncVO {

	use StdClassAdapter {
		__get as __adapterGet;
	}

	/**
	 * @param string $property
	 * @return mixed
	 */
	public function __get( $property ) {

		$mValue = $this->__adapterGet( $property );

		switch ( $property ) {
			case 'meta':
				$mValue = ( new SyncMetaVO() )->applyFromArray( $mValue );
				break;
			default:
				break;
		}

		return $mValue;
	}
}