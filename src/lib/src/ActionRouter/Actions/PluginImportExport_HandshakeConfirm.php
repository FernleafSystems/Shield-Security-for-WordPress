<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PluginImportExport_HandshakeConfirm extends PluginImportExport_Base {

	public const SLUG = 'importexport_handshake';

	protected function exec() {
		$this->getCon()->getModule_Plugin()->getImpExpController()->confirmExportHandshake();
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}