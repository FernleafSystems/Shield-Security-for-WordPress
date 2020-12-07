<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;

/**
 * Class ConfigVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Controller\Config
 * @property array  $properties
 * @property array  $requirements
 * @property array  $paths
 * @property array  $includes
 * @property array  $menu
 * @property array  $labels
 * @property array  $action_links
 * @property array  $meta
 * @property array  $plugin_meta
 * @property array  $upgrade_reqs
 * @property array  $version_upgrades
 *                                   -- not part of config file --
 * @property string $hash
 * @property string $previous_version
 * @property array  $update_first_detected
 */
class ConfigVO {

	use StdClassAdapter {
		__get as __adapterGet;
	}

	/**
	 * @var bool
	 */
	public $rebuilt = false;

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( $key ) {
		$val = $this->__adapterGet( $key );

		switch ( $key ) {

			case 'menu':
				$val = array_merge(
					[
						'show'           => true,
						'top_level'      => true,
						'do_submenu_fix' => true,
						'has_submenu'    => true,
						'title'          => 'undefined menu title',
						'callback'       => 'undefinedMenuCallback',
						'icon_image'     => 'pluginlogo_16x16.png',
					],
					is_array( $val ) ? $val : []
				);
				break;

			case 'previous_version':
				if ( empty( $val ) ) {
					$val = $this->properties[ 'version' ];
				}
				break;

			case 'update_first_detected':
				if ( empty( $val ) ) {
					$val = [];
				}
				break;

			default:
				break;
		}

		return $val;
	}
}