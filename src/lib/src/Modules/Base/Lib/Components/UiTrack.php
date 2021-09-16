<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Components;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

/**
 * @property array $selected_scans
 */
class UiTrack extends DynPropertiesClass {

	use PluginControllerConsumer;

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'selected_scans':
				if ( !is_array( $value ) || empty( $value ) ) {
					/** @var HackGuard\Options $opts */
					$opts = $this->getCon()
								 ->getModule_HackGuard()
								 ->getOptions();
					$value = $opts->getScanSlugs();
				}
				break;
			default:
				break;
		}

		return $value;
	}
}