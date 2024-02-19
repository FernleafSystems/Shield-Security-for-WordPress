<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Exceptions\PluginConfigInvalidException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class LoadModuleConfigs {

	use PluginControllerConsumer;

	/**
	 * @return ModConfigVO[]
	 * @throws PluginConfigInvalidException
	 */
	public function run() :array {
		$conCfg = self::con()->cfg;

		if ( !\is_array( $conCfg->config_spec ) ) {
			throw new PluginConfigInvalidException( 'invalid specification of modules' );
		}

		$configuration = ( new ConfigurationVO() )->applyFromArray( $conCfg->config_spec );
		self::con()->cfg->configuration = $configuration;

		$indexed = [];
		foreach ( $configuration->sections as $section ) {
			$indexed[ $section[ 'slug' ] ] = $section;
		}
		$configuration->sections = $indexed;

		$indexed = [];
		foreach ( $configuration->options as $option ) {
			$indexed[ $option[ 'key' ] ] = $option;
		}
		$configuration->options = $indexed;

		$legacyModuleCFGs = [];
		foreach ( $conCfg->config_spec[ 'modules' ] as $slug => $moduleProperties ) {

			$modCfg = new ModConfigVO();
			$modCfg->slug = $slug;
			$modCfg->properties = \array_merge( [
				'storage_key'         => $slug,
				'show_module_options' => true,
				'menu_priority'       => 100,
			], $moduleProperties );

			$modCfg->sections = $configuration->sectionsForModule( $modCfg->slug );
			$modCfg->options = $configuration->optsForModule( $modCfg->slug );

			/** @var ModConfigVO $modCfg */
			$modCfg = apply_filters( 'shield/load_mod_cfg', $modCfg, $slug );

			$legacyModuleCFGs[ $slug ] = $modCfg;
		}

		return $legacyModuleCFGs;
	}
}