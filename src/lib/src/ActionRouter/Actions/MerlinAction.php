<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\MerlinController;

class MerlinAction extends BaseAction {

	public const SLUG = 'merlin_action';

	protected function exec() {
		try {
			$response = ( new MerlinController() )->processFormSubmit( FormParams::Retrieve() );
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