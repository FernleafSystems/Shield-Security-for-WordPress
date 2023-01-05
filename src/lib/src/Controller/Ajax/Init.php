<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Ajax;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 17.0
 */
class Init {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return Services::WpGeneral()->isAjax();
	}

	protected function run() {
		add_action( 'wp_ajax_shield_action', function () {
			$this->ajaxAction();
		}, 1 );
		add_action( 'wp_ajax_nopriv_shield_action', function () {
			$this->ajaxAction( false );
		}, 1 );

		// this allows us to intercept specific AJAX requests before a block is enforced.
		add_action( 'shield/maybe_intercept_block_shield', function () {
			$this->ajaxAction( false );
		} );
		add_action( 'shield/maybe_intercept_block_crowdsec', function () {
			$this->ajaxAction( false );
		} );
	}

	private function ajaxAction( bool $forceDie = true ) {
		$req = Services::Request();
		$nonceAction = $req->request( 'exec' );

		// if the ajax action is part of the "allow" list, it may fail the nonce.
		// This is work around for front-end caching plugin that screw everything up.
		check_ajax_referer( $nonceAction, 'exec_nonce',
			$forceDie || !in_array( $nonceAction, $this->getAllowedNoPrivExecs() ) );

		try {
			ob_start();
			$response = [];
			$noise = ob_get_clean();
			$response = $this->normaliseAjaxResponse( $response );
		}
		catch ( ActionException $e ) {
			$response = [
				'success' => false,
				'message' => $e->getMessage(),
				'error'   => $e->getMessage(),
			];
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

	private function getAllowedNoPrivExecs() :array {
		return [];
	}
}