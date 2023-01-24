<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Ajax\Response;
use FernleafSystems\Wordpress\Services\Services;

class CaptureAjaxAction extends CaptureActionBase {

	protected function canRun() :bool {
		return $this->getCon()->this_req->wp_is_ajax && parent::canRun();
	}

	protected function theRun() {
		foreach (
			[
				'wp_ajax_'.ActionData::FIELD_SHIELD        => 1,
				'wp_ajax_nopriv_'.ActionData::FIELD_SHIELD => 1,
				'shield/maybe_intercept_block_shield'      => 10,
				'shield/maybe_intercept_block_crowdsec'    => 10,
			] as $hook => $priority
		) {
			add_action( $hook, function () {
				$this->ajaxAction();
			}, $priority );
		}
	}

	private function ajaxAction() {
		$con = $this->getCon();

		$req = Services::Request();
		try {
			$router = $con->action_router;
			ob_start();
			$response = $this->normaliseAjaxResponse(
				$router
					->action( $this->extractActionSlug(), $req->post, $router::ACTION_AJAX )
					->action_response_data
			);
		}
		catch ( ActionException $e ) {
			$response = [
				'success' => false,
				'message' => $e->getMessage(),
				'error'   => $e->getMessage(),
			];
		}
		finally {
			$noise = ob_get_clean();
		}

		if ( !empty( $response ) ) {
			( new Response() )->issue( [
				'success' => $response[ 'success' ] ?? false,
				'data'    => $response,
				'noise'   => $noise ?? ''
			] );
		}
	}

	/**
	 * We check for empty since if it's empty, there's nothing to normalize. It's a filter,
	 * so if we send something back non-empty, it'll be treated like a "handled" response and
	 * processing will finish
	 */
	protected function normaliseAjaxResponse( array $ajaxResponse ) :array {
		if ( !empty( $ajaxResponse ) ) {
			$ajaxResponse = array_merge(
				[
					'success'     => false,
					'page_reload' => false,
					'message'     => 'No AJAX Message provided',
					'html'        => '',
				],
				$ajaxResponse
			);
		}
		return $ajaxResponse;
	}
}