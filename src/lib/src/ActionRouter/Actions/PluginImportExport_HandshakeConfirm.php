<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;

class PluginImportExport_HandshakeConfirm extends PluginImportExport_Base {

	public const SLUG = 'importexport_handshake';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$mod->getImpExpController()->confirmExportHandshake();
		$this->response()->action_response_data = [
			'success' => true,
		];
	}
}