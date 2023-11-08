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
			if ( !empty( $module->opts()->getDef( 'rest_api' )[ 'publish' ] ) ) {
				try {
					$restClass = $module->findElementClass( 'Rest' );
					/** @var Rest|string $restClass */
					if ( @\class_exists( $restClass ) ) {
						$rest = new $restClass( $module->opts()->getDef( 'rest_api' ) );
						$rest->setMod( $module )->init();
					}
				}
				catch ( \Exception $e ) {
				}
			}
		}
	}
}