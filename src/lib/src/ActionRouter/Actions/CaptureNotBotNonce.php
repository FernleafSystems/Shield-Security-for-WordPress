<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;

class CaptureNotBotNonce extends BaseAction {

	use Traits\AuthNotRequired;
	use Traits\NonceVerifyNotRequired;

	public const SLUG = 'capture_not_bot_nonce';

	protected function exec() {
		$this->getCon()
			 ->getModule_IPs()
			 ->getBotSignalsController()
			 ->getHandlerNotBot()
			 ->sendNotBotNonceCookie();

		$this->response()->success = true;
		$this->response()->action_response_data = [
			'nonce' => ActionData::Build( CaptureNotBot::SLUG )[ ActionData::FIELD_NONCE ]
		];
	}
}