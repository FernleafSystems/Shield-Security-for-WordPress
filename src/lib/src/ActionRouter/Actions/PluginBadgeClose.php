<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PluginBadgeClose extends BaseAction {

	use Traits\AuthNotRequired;

	public const SLUG = 'plugin_badge_close';

	protected function exec() {
		$success = self::con()->comps->badge->setBadgeStateClosed();
		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $success ? 'Badge Closed' : 'Badge Not Closed'
		];
	}
}