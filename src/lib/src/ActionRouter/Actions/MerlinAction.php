<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;

class MerlinAction extends BaseAction {

	public const SLUG = 'merlin_action';

	protected function exec() {
		try {
			$response = self::con()
							->getModule_Plugin()
							->getWizardCon()
							->processFormSubmit( FormParams::Retrieve() );
			$success = $response->success;
			$msg = $response->getRelevantMsg();
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = $e->getMessage();
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => $response->data[ 'page_reload' ] ?? false,
			'show_toast'  => true,
		];
	}
}