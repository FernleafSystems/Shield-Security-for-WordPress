<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property array[]    $modules
 * @property SyncMetaVO $meta
 */
class SyncVO extends DynPropertiesClass {

	/**
	 * @inheritDoc
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