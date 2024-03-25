<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

abstract class ConfigBase extends BaseCmd {

	/**
	 * @return string[]
	 */
	protected function getOptionsForWpCli( ?string $module = null ) :array {
		$config = self::con()->cfg->configuration;
		return \array_filter(
			\array_keys( empty( $module ) ? $config->options : $config->optsForModule( $module ) ),
			function ( $key ) {
				return self::con()->opts->optDef( $key )[ 'section' ] !== 'section_hidden';
			}
		);
	}
}