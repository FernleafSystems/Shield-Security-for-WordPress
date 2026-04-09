<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PluginImportExport_UpdateNotified extends PluginImportExport_Base {

	public const SLUG = 'importexport_updatenotified';

	protected function exec() {
		self::con()->comps->import_export->runOptionsUpdateNotified();
		$this->response()->setPayload()->setPayloadSuccess( true );
	}
}
