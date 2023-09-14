<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Components;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @property array $selected_scans
 */
class UiTrack extends DynPropertiesClass {

	use PluginControllerConsumer;

	/**
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'selected_scans':
				if ( empty( $value ) || !\is_array( $value ) ) {
					$value = self::con()
								 ->getModule_HackGuard()
								 ->getScansCon()
								 ->getScanSlugs();
				}
				break;
			default:
				break;
		}

		return $value;
	}
}