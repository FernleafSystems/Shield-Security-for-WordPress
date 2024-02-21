<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Exceptions\PluginConfigInvalidException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class LoadModuleConfigs {

	use PluginControllerConsumer;

	/**
	 * @throws PluginConfigInvalidException
	 */
	public function run() :ConfigurationVO {
		$cfgSpec = self::con()->cfg->config_spec ?? null;

		if ( !\is_array( $cfgSpec ) ) {
			throw new PluginConfigInvalidException( 'invalid specification of modules' );
		}
		if ( empty( $cfgSpec[ 'modules' ] ) ) {
			throw new PluginConfigInvalidException( 'No modules specified in the plugin config.' );
		}

		$configuration = ( new ConfigurationVO() )->applyFromArray( $cfgSpec );

		$indexed = [];
		$hiddenSections = [];
		foreach ( $configuration->sections as $section ) {
			$indexed[ $section[ 'slug' ] ] = $section;
			if ( $section[ 'hidden' ] ?? false ) {
				$hiddenSections[] = $section[ 'slug' ];
			}
		}
		$configuration->sections = $indexed;

		$indexed = [];
		foreach ( $configuration->options as $option ) {
			$option[ 'hidden' ] = \in_array( $option[ 'section' ], $hiddenSections ) || ( $option[ 'hidden' ] ?? false );
			$indexed[ $option[ 'key' ] ] = $option;
		}
		$configuration->options = $indexed;

		$modules = [];
		foreach ( $configuration->modules as $slug => $moduleProps ) {
			$modules[ $slug ] = \array_merge( [
				'storage_key'         => $slug,
				'show_module_options' => true,
				'load_priority'       => 10,
				'menu_priority'       => 100,
			], $moduleProps );
		}

		// Order Modules based on their load priority
		\uasort( $modules, function ( array $a, array $b ) {
			if ( $a[ 'load_priority' ] == $b[ 'load_priority' ] ) {
				return 0;
			}
			return ( $a[ 'load_priority' ] < $b[ 'load_priority' ] ) ? -1 : 1;
		} );

		$configuration->modules = $modules;

		return $configuration;
	}
}