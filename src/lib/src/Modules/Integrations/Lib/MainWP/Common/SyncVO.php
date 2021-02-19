<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * Class SyncVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common
 * @property array[]    $modules
 * @property SyncMetaVO $meta
 */
class SyncVO extends DynPropertiesClass {

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {

		$mValue = parent::__get( $key );

		switch ( $key ) {
			case 'meta':
				$mValue = ( new SyncMetaVO() )->applyFromArray( $mValue );
				break;
			default:
				break;
		}

		return $mValue;
	}
}