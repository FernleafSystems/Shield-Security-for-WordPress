<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;

class PluginImportExport_Enable extends BaseAction {

	use SecurityAdminRequired;

	public const SLUG = 'importexport_enable';

	protected function exec() {
		$con = self::con();
		$importExport = new ImportExportController();
		if ( !$importExport->isSyncAvailable() ) {
			$this->response()->setPayload( [
				'message'     => __( 'Import/export sync is not available on this plan.', 'wp-simple-firewall' ),
				'page_reload' => false,
			] )->setPayloadSuccess( false );
			return;
		}

		$con->opts->optSet( 'importexport_enable', 'Y' )->store();
		$importExport->refreshRegistryAndScheduleQueueIfEnabled();

		$this->response()->setPayload( [
			'message'     => __( 'Import and export has been enabled. Reloading...', 'wp-simple-firewall' ),
			'page_reload' => true,
		] )->setPayloadSuccess( true );
	}
}
