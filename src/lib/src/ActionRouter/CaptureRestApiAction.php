<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest;

class CaptureRestApiAction extends CaptureActionBase {

	protected function canRun() :bool {
		return self::con()->is_rest_enabled;
	}

	protected function run() {
		add_action( 'rest_api_init', function () {
			$this->theRun();
		} );
	}

	protected function theRun() {
		foreach ( self::con()->modules as $module ) {
			$restProperties = $module->cfg->properties[ 'rest_api' ] ?? [];
//			error_log( var_export( $restProperties, true ) );
			if ( !empty( $restProperties[ 'publish' ] ) ) {
				try {
					$restClass = $module->findElementClass( 'Rest' );
					/** @var Rest|string $restClass */
					if ( @\class_exists( $restClass ) ) {
						$rest = new $restClass( $restProperties );
						$rest->setMod( $module )->init();
					}
				}
				catch ( \Exception $e ) {
				}
			}
		}
	}
}