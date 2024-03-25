<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property array[]    $modules
 * @property array[]    $options
 * @property array[]    $integrity
 * @property array[]    $scan_issues
 * @property SyncMetaVO $meta
 */
class SyncVO extends DynPropertiesClass {

	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'meta':
				$value = ( new SyncMetaVO() )->applyFromArray( \is_array( $value ) ? $value : [] );
				break;
			default:
				break;
		}

		return $value;
	}
}