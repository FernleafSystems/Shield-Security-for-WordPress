<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Config;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property array  $properties
 * @property array  $meta
 * @property array  $options
 *
 * @property array  $requirements
 * @property array  $paths
 * @property array  $includes
 * @property array  $menu
 * @property array  $labels
 * @property array  $action_links
 * @property array  $plugin_meta
 * @property array  $upgrade_reqs
 * @property array  $version_upgrades
 * @property array  $reqs_rest
 *                                   -- not part of config file --
 * @property string $hash
 * @property string $previous_version
 * @property array  $update_first_detected
 */
class ModConfigVO extends DynPropertiesClass {

	/**
	 * @var bool
	 */
	public $rebuilt = false;

	/**
	 * @return mixed
	 */
	public function __get( string $key ) {
		$val = parent::__get( $key );

		switch ( $key ) {
			default:
				break;
		}

		return $val;
	}
}