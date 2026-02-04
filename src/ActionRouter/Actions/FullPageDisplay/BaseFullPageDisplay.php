<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\NonceVerifyNotRequired;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseFullPageDisplay extends BaseAction {

	use AuthNotRequired;
	use NonceVerifyNotRequired;

	protected function exec() {
		$this->setResponse(
			self::con()->action_router->action(
				Render::class,
				[
					'render_action_slug' => $this->action_data[ 'render_slug' ],
					'render_action_data' => $this->action_data[ 'render_data' ] ?? [],
				]
			)
		);
	}

	/**
	 * display page and die().
	 */
	protected function postExec() {
		$this->issueHeaders();
		$this->pushContent();
		$this->complete();
	}

	protected function pushContent() {
		echo $this->response()->action_response_data[ 'render_output' ];
	}

	protected function isCacheDisabled() :bool {
		return true;
	}

	protected function issueHeaders() {
		\http_response_code( $this->getResponseCode() );
		nocache_headers();
		if ( $this->isCacheDisabled() ) {
			Services::WpGeneral()->turnOffCache();
		}
	}

	protected function complete() {
		die();
	}

	protected function getResponseCode() :int {
		return $this->isSuccess() ? $this->getSuccessCode() : $this->getFailureCode();
	}

	protected function getFailureCode() :int {
		return 403;
	}

	protected function getSuccessCode() :int {
		return 200;
	}

	protected function isSuccess() :bool {
		return $this->response()->success ?? false;
	}

	protected function getRequiredDataKeys() :array {
		return [
			'render_slug'
		];
	}
}