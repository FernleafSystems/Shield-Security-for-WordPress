<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyStrings {

	use PluginControllerConsumer;

	public function run() {

		$descNotArray = [];

		$strings = new StringsOptions();

		foreach ( self::con()->cfg->configuration->options as $key => $option ) {

			if ( !\in_array( $option[ 'section' ], [ 'section_hidden', 'section_deprecated' ] ) ) {
				try {
					if ( \count( \array_filter( $strings->getFor( $key ) ) ) !== 3 ) {
						$descNotArray[] = $key;
					}
				}
				catch ( \Exception $e ) {
					var_dump( 'no strings for : '.$key );
				}
			}
		}

		var_dump( 'Not all strings available for the following options:' );
		var_dump( $descNotArray );
	}
}