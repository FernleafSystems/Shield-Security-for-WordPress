<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;

/**
 * @deprecated 19.2.0
 */
class CaptureNotBotNonce extends BaseAction {

	use Traits\AuthNotRequired;
	use Traits\NonceVerifyNotRequired;

	public const SLUG = 'capture_not_bot_nonce';

	protected function exec() {
		$this->response()->success = true;
		$this->response()->action_response_data = [
			'nonce' => ActionData::Build( CaptureNotBot::class )[ ActionData::FIELD_NONCE ]
		];
	}
}