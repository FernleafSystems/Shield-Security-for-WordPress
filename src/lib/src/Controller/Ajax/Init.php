<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Ajax;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Init {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return Services::WpGeneral()->isAjax();
	}

	protected function run() {
		add_action( 'wp_ajax_'.$this->getCon()->prefix(), function () {
			$this->ajaxAction();
		} );
		add_action( 'wp_ajax_nopriv_'.$this->getCon()->prefix(), function () {
			$this->ajaxAction( false );
		} );
	}

	private function ajaxAction( bool $forceDie = true ) {
		$con = $this->getCon();
		$req = Services::Request();
		$nonceAction = $req->request( 'exec' );

		// if the ajax action is part of the "allow" list, it may fail the nonce.
		// This is work around for front-end caching plugin that screw everything up.
		check_ajax_referer( $nonceAction, 'exec_nonce',
			$forceDie || !in_array( $nonceAction, $this->getAllowedNoPrivExecs() ) );

		/** @var callable[] $handlers */
		$handlers = apply_filters( $con->prefix( 'ajax_handlers' ), [], Services::WpUsers()->isUserLoggedIn() );
		if ( isset( $handlers[ $nonceAction ] ) ) {
			ob_start();
			$response = $handlers[ $nonceAction ]();
			$noise = ob_get_clean();
			$response = $this->normaliseAjaxResponse( $response );
		}
		else {
			$response = [
				'success' => false,
				'error'   => 'There was no AJAX handler available for '.$nonceAction
			];
			$noise = [];
		}

		( new Response() )->issue(
			[
				'success' => $response[ 'success' ] ?? false,
				'data'    => $response,
				'noise'   => $noise
			],
			false
		);
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