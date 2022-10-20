<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

class DynamicPageLoad extends BaseAction {

	const SLUG = 'dynamic_page_load';

	protected function exec() {
		$resp = $this->response();
		try {
			$resp->action_response_data = $this->getCon()
											   ->getModule_Insights()
											   ->getActionRouter()
											   ->action(
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