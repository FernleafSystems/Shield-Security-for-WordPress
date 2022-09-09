<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights;

use FernleafSystems\Wordpress\Plugin\Shield;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		$map = parent::getAjaxActionCallbackMap( $isAuth );
		if ( $isAuth ) {
			$map = array_merge( $map, [
				'dynamic_load'  => [ $this, 'ajaxExec_DynamicLoad' ],
				'merlin_action' => [ $this, 'ajaxExec_MerlinAction' ],
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
			$msg = 'render success';
		}
		catch ( \Exception $e ) {
			$msg = $e->getMessage();
			$success = false;
		}

		return array_merge(
			[
				'success'    => $success,
				'message'    => $msg,
				'html'       => 'no html',
				'show_toast' => !$success,
			],
			$pageData ?? []
		);
	}

	public function ajaxExec_MerlinAction() :array {
		try {
			( new Shield\Modules\Insights\Lib\Merlin\MerlinController() )
				->setMod( $this->getMod() )
				->processFormSubmit( Shield\Modules\Base\Lib\Request\FormParams::Retrieve() );
			$success = true;
			$msg = __( 'Option updated successfully.' );
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		return [
			'success' => $success,
			'message' => $msg,
		];
	}
}