<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\ConfigurationVO;

/**
 * @property array                 $properties
 * @property array{
 *     modules:array,
 *     sections:array,
 *     options:array,
 *     defs:array,
 *     admin_notices:array,
 *     }                           $config_spec
 * @property array                 $requirements
 * @property array                 $paths
 * @property array                 $includes
 * @property array                 $menu
 * @property array                 $labels
 * @property array                 $action_links
 * @property array                 $meta
 * @property array                 $plugin_meta
 * @property array                 $upgrade_reqs
 * @property array                 $version_upgrades
 *                                   -- not part of config file --
 * @property string                $hash
 * @property string                $previous_version
 * @property array                 $update_first_detected
 * @property Modules\ModConfigVO[] $mods_cfg
 * @property ConfigurationVO       $configuration
 */
class ConfigVO extends DynPropertiesClass {

	/**
	 * @var bool
	 */
	public $rebuilt = false;

	public $builtHash = '';

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
			case 'update_first_detected':
			case 'meta':
			case 'plugin_meta':
			case 'upgrade_reqs':
				if ( !\is_array( $val ) ) {
					$val = [];
				}
				break;

			case 'mods_cfg':
				$val = \array_filter( \array_map(
					function ( $cfg ) {
						return \is_array( $cfg ) ? ( new Modules\ModConfigVO() )->applyFromArray( $cfg ) : null;
					},
					\is_array( $val ) ? $val : []
				) );
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
			case 'mods_cfg':
				$value = \array_filter( \array_map(
					function ( $cfg ) {
						return empty( $cfg ) ? null : $cfg->getRawData();
					},
					\is_array( $value ) ? $value : []
				) );
				break;
			case 'configuration':
				$value = empty( $value ) ? null : $value->getRawData();
				break;

			default:
				break;
		}
		parent::__set( $key, $value );
	}
}