<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	ActionException,
	InvalidActionNonceException,
	SecurityAdminRequiredException,
	UserAuthRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ResponseAdapter\ResponseAdapterFactory;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\{
	AuthRefreshRequest,
	ExternalEntryGuard,
	ResponseEnvelopeNormalizer
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Ajax\Response;
use FernleafSystems\Wordpress\Services\Services;

class CaptureAjaxAction extends CaptureActionBase {

	protected function canRun() :bool {
		return self::con()->this_req->wp_is_ajax && parent::canRun();
	}

	protected function transportData() :array {
		return Services::Request()->post;
	}

	protected function guardedEntry() :string {
		return ExternalEntryGuard::ENTRY_AJAX;
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
		$transport = $this->transportData();
		$slug = $this->requestedSlug( $transport );
		$guard = new ExternalEntryGuard();

		try {
			\ob_start();
			if ( $this->isFromExpiredSession( $guard, $slug, $transport ) ) {
				$statusCode = 401;
				$response = ResponseEnvelopeNormalizer::forAjaxAuthRefresh();
			}
			else {
				$routedResponse = ( new ResponseAdapterFactory() )
					->forActionType( ActionRoutingController::ACTION_AJAX )
					->adapt( $guard->dispatch( $this->guardedEntry(), $slug, $transport ) );
				$response = $this->normaliseAjaxResponse( $routedResponse->payload() );
				$statusCode = $routedResponse->statusCode();
			}
		}
		catch ( InvalidActionNonceException $e ) {
			$statusCode = 401;
			if ( Services::WpUsers()->isUserLoggedIn() ) {
				// The page's nonce was issued to a different login session of this user: ask for a reload.
				$response = ResponseEnvelopeNormalizer::forAjaxSessionChanged();
			}
			else {
				$msg = ResponseEnvelopeNormalizer::nonceFailedMessage();
				$response = [
					'success' => false,
					'message' => $msg,
					'error'   => $msg,
				];
			}
		}
		catch ( SecurityAdminRequiredException $e ) {
			$statusCode = 401;
			$msg = ResponseEnvelopeNormalizer::securityAdminRequiredMessage();
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
		catch ( \Throwable $e ) {
			// A fault in the action, not a refusal. The request still gets a controlled response.
			\error_log( \sprintf( 'Shield AJAX action %s failed: %s', $slug, $e->getMessage() ) );
			$statusCode = 500;
			$msg = __( 'An unexpected error occurred.', 'wp-simple-firewall' );
			$response = [
				'success' => false,
				'message' => $msg,
				'error'   => $msg,
			];
		}
		finally {
			$noise = \ob_get_clean();
		}

		return empty( $response )
			? []
			: [
				'success'     => (bool)( $response[ 'success' ] ?? false ),
				'data'        => $response,
				'noise'       => $noise,
				'status_code' => $statusCode
			];
	}

	/**
	 * A request from a page that was loaded while logged in, whose login session has since expired: it arrives
	 * logged out, with a nonce that no longer verifies. It's told to log in again and isn't recorded as a refusal.
	 */
	private function isFromExpiredSession( ExternalEntryGuard $guard, string $slug, array $transport ) :bool {
		$expired = false;
		if ( AuthRefreshRequest::isRequested() && !Services::WpUsers()->isUserLoggedIn() ) {
			try {
				$guard->verifyProof( $slug, $this->guardedEntry(), $transport );
			}
			catch ( InvalidActionNonceException $e ) {
				$expired = true;
			}
		}
		return $expired;
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
