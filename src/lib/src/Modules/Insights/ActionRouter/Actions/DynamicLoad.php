<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Requests\DynamicContentLoader;

class DynamicLoad extends BaseAction {

	const SLUG = 'dynamic_load';

	protected function exec() {
		$resp = $this->response();
		try {
			$resp->action_response_data = ( new DynamicContentLoader() )
				->setMod( $this->getMod() )
				->build( FormParams::Retrieve() );
			$resp->success = true;
		}
		catch ( \Exception $e ) {
			$resp->success = false;
			$resp->message = $e->getMessage();
		}
	}
}