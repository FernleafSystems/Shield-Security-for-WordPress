<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PluginImportExport_SetEnabled extends BaseAction {

	use Traits\SecurityAdminRequired;

	public const SLUG = 'importexport_set_enabled';

	protected function exec() {
		$enabled = (string)( $this->action_data[ 'enabled' ] ?? '' );
		if ( !\in_array( $enabled, [ 'Y', 'N' ], true ) ) {
			$this->response()->setPayload( [
				'message'     => __( 'Invalid import/export enabled state.', 'wp-simple-firewall' ),
				'page_reload' => false,
			] )->setPayloadSuccess( false );
			return;
		}

		try {
			self::con()->comps->import_export->setAutomaticImportExportEnabled( $enabled === 'Y' );
			$success = true;
			$message = $enabled === 'Y'
				? __( 'Import and export has been enabled. Reloading...', 'wp-simple-firewall' )
				: __( 'Import and export has been disabled. Reloading...', 'wp-simple-firewall' );
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
