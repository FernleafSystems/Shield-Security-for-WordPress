<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\DynamicLoad;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\BaseAction;

abstract class Base extends BaseAction {

	protected function exec() {
		$resp = $this->response();
		try {
			$resp->action_response_data = [
				'html'       => $this->getContent(),
				'page_url'   => $this->getPageUrl(),
				'page_title' => $this->getPageTitle(),
			];
			$resp->success = true;
		}
		catch ( \Exception $e ) {
			$resp->success = false;
			$resp->message = $e->getMessage();
		}
	}

	abstract protected function getContent() :string;

	abstract protected function getPageTitle() :string;

	abstract protected function getPageUrl() :string;
}