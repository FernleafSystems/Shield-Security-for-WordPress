<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\ConfigurationVO;

/**
 * @property array            $properties
 * @property array{
 *     modules:array,
 *     sections:array,
 *     options:array,
 *     defs:array,
 *     admin_notices:array,
 *     }                      $config_spec
 * @property array            $requirements
 * @property array            $paths
 * @property array            $includes
 * @property array            $menu
 * @property array            $labels
 * @property array            $action_links
 * @property array            $meta
 * @property array            $plugin_meta
 * @property array            $version_upgrades
 *                                   -- not part of config file --
 * @property string           $hash
 * @property string           $previous_version
 * @property ?ConfigurationVO $configuration
 */
class ConfigVO extends DynPropertiesClass {

	public bool $rebuilt = false;

	public string $builtHash = '';

	public function version() :string {
		return $this->properties[ 'version' ];
	}

	/**
	 * @return mixed
	 */
	public function __get( string $key ) {
		$val = parent::__get( $key );

		switch ( $key ) {

			case 'menu':
				$val = \array_merge(
					[
						'show'           => true,
						'top_level'      => true,
						'do_submenu_fix' => true,
						'has_submenu'    => true,
						'title'          => 'undefined menu title',
						'callback'       => 'undefinedMenuCallback',
					],
					\is_array( $val ) ? $val : []
				);
				break;

			case 'modules':
			case 'meta':
			case 'plugin_meta':
				if ( !\is_array( $val ) ) {
					$val = [];
				}
				break;

			case 'configuration':
				$val = \is_array( $val ) ? ( new Modules\ConfigurationVO() )->applyFromArray( $val ) : null;
				break;

			default:
				break;
		}

		return $val;
	}

	public function __set( string $key, $value ) {
		switch ( $key ) {
			case 'configuration':
				$value = empty( $value ) ? null : $value->getRawData();
				break;

			default:
				break;
		}
		parent::__set( $key, $value );
	}
}