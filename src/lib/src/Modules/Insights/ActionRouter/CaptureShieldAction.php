<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter;

use FernleafSystems\Wordpress\Services\Services;

class CaptureShieldAction extends CaptureActionBase {

	protected function canRun() :bool {
		return !$this->getCon()->this_req->wp_is_ajax && parent::canRun();
	}

	protected function theRun() {
		$req = Services::Request();
		try {
			$router = $this->getCon()->getModule_Insights()->getActionRouter();
			$this->actionResponse = $router->action( $this->extractActionSlug(), array_merge( $req->query, $req->post ) );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	protected function postRun() {
		if ( !empty( $this->actionResponse ) && !empty( $this->actionResponse->next_step ) ) {
			switch ( $this->actionResponse->next_step[ 'type' ] ) {
				case 'redirect':
					Services::Response()->redirect( $this->actionResponse->next_step[ 'url' ] );
					break;
				default:
					break;
			}
		}
	}
}