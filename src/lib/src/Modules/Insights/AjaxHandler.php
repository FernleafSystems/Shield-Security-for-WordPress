<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'dynamic_load':
				$response = $this->ajaxExec_DynamicLoad();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_DynamicLoad() :array {

		try {
			$pageData = ( new Lib\Requests\DynamicPageLoader() )
				->setMod( $this->getMod() )
				->build( Shield\Modules\Base\Lib\Request\FormParams::Retrieve() );
			$success = true;
		}
		catch ( \Exception $e ) {
			$pageData = [
				'message' => $e->getMessage(),
				'success' => false,
			];
			$success = false;
		}

		return array_merge(
			[
				'success'    => false,
				'message'    => 'no msg',
				'html'       => 'no html',
				'show_toast' => !$success,
			],
			$pageData
		);
	}
}