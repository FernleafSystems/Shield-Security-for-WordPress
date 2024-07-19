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

		// clean out strings from the config spec to reduce size (these Strings are necessary for Central, only).
		foreach ( $cfgSpec[ 'sections' ] as &$section ) {
			unset( $section[ 'title' ] );
			unset( $section[ 'title_short' ] );
		}
		foreach ( $cfgSpec[ 'options' ] as &$option ) {
			unset( $option[ 'name' ] );
			unset( $option[ 'summary' ] );
			unset( $option[ 'description' ] );
		}

		$normalizer = new NormaliseConfigComponents();
		$configuration = ( new ConfigurationVO() )->applyFromArray( $cfgSpec );
		$configuration->sections = $normalizer->indexSections( $configuration->sections );
		$configuration->options = $normalizer->indexOptions( $configuration->options );

		self::con()->cfg->config_spec = null;

		return $configuration;
	}
}