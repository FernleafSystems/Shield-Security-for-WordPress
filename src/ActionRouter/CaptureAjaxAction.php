<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	ActionException,
	InvalidActionNonceException,
	SecurityAdminRequiredException,
	UserAuthRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\AuthRefreshRequest;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ResponseEnvelopeNormalizer;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Ajax\Response;
use FernleafSystems\Wordpress\Services\Services;

class CaptureAjaxAction extends CaptureActionBase {

	protected function canRun() :bool {
		return self::con()->this_req->wp_is_ajax && parent::canRun();
	}

	protected function transportData() :array {
		$post = Services::Request()->post;
		return \is_array( $post ) ? $post : [];
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
		$issuePayload = $this->buildAjaxIssuePayload();
		if ( $issuePayload !== [] ) {
			$this->issueAjaxResponse( $issuePayload );
		}
	}

	protected function buildAjaxIssuePayload() :array {
		$con = self::con();
		$transport = $this->transportData();

		try {
			\ob_start();
			$routedResponse = $con->action_router->executor()->execute(
				$this->extractActionSlugFromTransport( $transport ),
				$transport,
				ActionRoutingController::ACTION_AJAX
			);
			$response = $this->normaliseAjaxResponse( $routedResponse->payload() );
			$statusCode = $routedResponse->statusCode();
		}
		catch ( InvalidActionNonceException $e ) {
			$statusCode = 401;
			$msg = __( 'Nonce Failed.', 'wp-simple-firewall' );
			$response = [
				'success' => false,
				'message' => $msg,
				'error'   => $msg,
			];
		}
		catch ( SecurityAdminRequiredException $e ) {
			$statusCode = 401;
			$msg = \implode( ' ', [
				__( 'You must be authorised as a Security Admin to perform this action.', 'wp-simple-firewall' ),
				__( 'You may need to reload this page to continue.', 'wp-simple-firewall' ),
			] );
			$response = [
				'success' => false,
				'message' => $msg,
				'error'   => $msg,
			];
		}
		catch ( UserAuthRequiredException $e ) {
			$statusCode = 401;
			$response = AuthRefreshRequest::isRequested()
				? ResponseEnvelopeNormalizer::forAjaxAuthRefresh()
				: [
					'success' => false,
					'message' => $e->getMessage(),
					'error'   => $e->getMessage(),
				];
		}
		catch ( ActionException $e ) {
			$statusCode = empty( $e->getCode() ) ? 400 : $e->getCode();
			$response = [
				'success' => false,
				'message' => $e->getMessage(),
				'error'   => $e->getMessage(),
			];
		}
		finally {
			$noise = \ob_get_clean();
		}

		return empty( $response )
			? []
			: [
				'success'     => (bool)( $response[ 'success' ] ?? false ),
				'data'        => \array_diff_key( $response, \array_flip( [
					'action_data',
					/** TODO: refine action process to ensure that excess data isn't included */
				] ) ),
				'noise'       => $noise,
				'status_code' => $statusCode
			];
	}

	protected function issueAjaxResponse( array $issuePayload ) :void {
		( new Response() )->issue( $issuePayload );
	}

	/**
	 * We check for empty since if it's empty, there's nothing to normalize. It's a filter,
	 * so if we send something back non-empty, it'll be treated like a "handled" response and
	 * processing will finish
	 */
	protected function normaliseAjaxResponse( array $ajaxResponse ) :array {
		if ( !empty( $ajaxResponse ) ) {
			$ajaxResponse = ResponseEnvelopeNormalizer::forAjax( $ajaxResponse );
		}
		return $ajaxResponse;
	}
}
