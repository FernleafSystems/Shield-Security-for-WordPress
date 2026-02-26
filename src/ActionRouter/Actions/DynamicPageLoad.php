<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminNotRequired;

class DynamicPageLoad extends BaseAction {

	use SecurityAdminNotRequired;

	public const SLUG = 'dynamic_page_load';

	protected function exec() {
		$resp = $this->response();
		try {
			$childPayload = self::con()->action_router->action(
				$this->action_data[ 'dynamic_load_params' ][ 'dynamic_load_slug' ],
				$this->action_data[ 'dynamic_load_params' ][ 'dynamic_load_data' ]
			)->payload();
			$resp->setPayload( $childPayload );
			$resp->setPayloadSuccess( true );
		}
		catch ( \Exception $e ) {
			$resp->setPayloadSuccess( false );
			$resp->message = $e->getMessage();
		}
	}

	protected function getRequiredDataKeys() :array {
		return [
			'dynamic_load_params',
		];
	}
}
