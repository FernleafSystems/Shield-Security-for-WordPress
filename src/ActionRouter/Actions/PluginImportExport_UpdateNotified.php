<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PluginImportExport_UpdateNotified extends BaseAction {

	use Traits\AuthNotRequired;
	use Traits\NonceVerifyNotRequired;

	public const SLUG = 'importexport_updatenotified';

	protected function exec() {
		self::con()->comps->import_export->runOptionsUpdateNotified();
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}