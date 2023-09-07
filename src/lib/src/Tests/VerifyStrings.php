<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class VerifyStrings {

	use PluginControllerConsumer;

	public function run() {

		$descNotArray = [];

		foreach ( self::con()->modules as $module ) {
			foreach ( $module->opts()->getVisibleOptionsKeys() as $visibleOptionsKey ) {
				try {
					$strings = $module->getStrings()->getOptionStrings( $visibleOptionsKey );
					if ( !\is_array( $strings[ 'description' ] ) ) {
						$descNotArray[] = $visibleOptionsKey;
					}
				}
				catch ( \Exception $e ) {
					var_dump( 'no strings for : '.$visibleOptionsKey );
				}
			}
		}

		var_dump( 'Description not array:' );
		var_dump( $descNotArray );
	}
}