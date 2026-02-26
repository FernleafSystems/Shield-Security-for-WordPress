<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

abstract class Base extends BaseAction {

	use SecurityAdminNotRequired;

	protected function exec() {
		$resp = $this->response();
		try {
			$resp->setPayload( [
				'success'    => true,
				'html'       => $this->getContent(),
				'page_url'   => $this->getPageUrl(),
				'page_title' => $this->getPageTitle(),
			] );
		}
		catch ( \Exception $e ) {
			$resp->setPayloadSuccess( false );
			$resp->message = $e->getMessage();
		}
	}

	abstract protected function getContent() :string;

	abstract protected function getPageTitle() :string;

	abstract protected function getPageUrl() :string;
}
