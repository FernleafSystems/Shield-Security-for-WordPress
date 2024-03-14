<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyStrings {

	use PluginControllerConsumer;

	public function run() {

		$descNotArray = [];

		$strings = new StringsOptions();

		foreach ( self::con()->modules as $module ) {

			$keys = \array_keys( \array_filter(
				self::con()->cfg->configuration->optsForModule( $module->cfg->slug ),
				function ( array $optDef ) {
					return $optDef[ 'section' ] !== 'section_hidden';
				}
			) );

			foreach ( $keys as $optKey ) {
				try {
					$strings = $strings->getFor( $optKey );
					if ( !\is_array( $strings[ 'description' ] ) ) {
						$descNotArray[] = $optKey;
					}
				}
				catch ( \Exception $e ) {
					var_dump( 'no strings for : '.$optKey );
				}
			}
		}

		var_dump( 'Descriptions not array:' );
		var_dump( $descNotArray );
	}
}