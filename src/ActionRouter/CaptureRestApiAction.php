<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

class CaptureRestApiAction extends CaptureActionBase {

	protected function canRun() :bool {
		return true;
	}

	protected function run() {
		add_action( 'rest_api_init', fn() => $this->theRun() );
	}

	protected function theRun() {
		self::con()->comps->rest->publish = true;
		try {
			self::con()->comps->rest->init();
		}
		catch ( \Exception $e ) {
		}
	}
}