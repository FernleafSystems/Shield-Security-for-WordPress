<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

class CaptureRestApiAction extends CaptureActionBase {

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		add_action( 'rest_api_init', function () {
			$this->theRun();
		} );
	}

	protected function theRun() {
		$restHandler = self::con()->comps->rest;
		if ( !empty( $restHandler ) ) {
			$restHandler->publish = true;
			try {
				$restHandler->init();
			}
			catch ( \Exception $e ) {
			}
		}
	}
}