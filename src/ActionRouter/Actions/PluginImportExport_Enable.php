<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;

class PluginImportExport_Enable extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'importexport_enable';

	protected function exec() {
		$importExport = self::con()->comps->import_export;

		try {
			$importExport->enableAutomaticImportExport();
		}
		catch ( \Throwable $e ) {
			$this->response()->setPayload( [
				'message'     => $e->getMessage(),
				'page_reload' => false,
			] )->setPayloadSuccess( false );
			return;
		}

		$this->response()->setPayload( [
			'message'     => __( 'Import and export has been enabled. Reloading...', 'wp-simple-firewall' ),
			'page_reload' => true,
		] )->setPayloadSuccess( true );
	}
}
