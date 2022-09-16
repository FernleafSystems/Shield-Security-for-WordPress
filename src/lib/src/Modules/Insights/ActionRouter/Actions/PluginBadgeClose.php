<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;

class PluginBadgeClose extends PluginBase {

	use Traits\AuthNotRequired;

	const SLUG = 'plugin_badge_close';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$success = $mod->getPluginBadgeCon()->setBadgeStateClosed();
		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $success ? 'Badge Closed' : 'Badge Not Closed'
		];
	}
}