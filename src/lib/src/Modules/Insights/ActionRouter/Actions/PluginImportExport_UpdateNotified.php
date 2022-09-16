<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;

class PluginImportExport_UpdateNotified extends PluginBase {

	use Traits\AuthNotRequired;
	use Traits\NonceVerifyNotRequired;

	const SLUG = 'importexport_updatenotified';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$mod->getImpExpController()->runOptionsUpdateNotified();
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}