<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'dynamic_load' => [ $this, 'ajaxExec_DynamicLoad' ],
			] );
		}
		return $map;
	}

	public function ajaxExec_DynamicLoad() :array {
		try {
			$pageData = ( new Lib\Requests\DynamicContentLoader() )
				->setMod( $this->getMod() )
				->build( Shield\Modules\Base\Lib\Request\FormParams::Retrieve() );
			$success = true;
		}
		catch ( \Exception $e ) {
			$pageData = [
				'success' => false,
				'message' => $e->getMessage(),
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