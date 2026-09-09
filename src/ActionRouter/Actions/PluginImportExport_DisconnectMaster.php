<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;

class PluginImportExport_DisconnectMaster extends BaseAction {

	use Traits\SecurityAdminRequired;

	public const SLUG = 'importexport_disconnect_master';

	protected function exec() {
		try {
			( new ImportExportController() )->disconnectMasterSite();
			$success = true;
			$message = __( 'Master site disconnected. Reloading...', 'wp-simple-firewall' );
		}
		catch ( \Throwable $e ) {
			$success = false;
			$message = $e->getMessage();
		}

		$this->response()->setPayload( [
			'message'     => $message,
			'page_reload' => $success,
		] )->setPayloadSuccess( $success );
	}
}
