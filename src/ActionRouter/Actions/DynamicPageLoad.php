<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class DynamicPageLoad extends BaseAction {

	use SecurityAdminNotRequired;

	public const SLUG = 'dynamic_page_load';

	protected function exec() {
		$resp = $this->response();
		try {
			$resp->action_response_data = self::con()->action_router->action(
				$this->action_data[ 'dynamic_load_params' ][ 'dynamic_load_slug' ],
				$this->action_data[ 'dynamic_load_params' ][ 'dynamic_load_data' ]
			)->action_response_data;
			$resp->success = true;
		}
		catch ( \Exception $e ) {
			$resp->success = false;
			$resp->message = $e->getMessage();
		}
	}

	protected function getRequiredDataKeys() :array {
		return [
			'dynamic_load_params',
		];
	}
}