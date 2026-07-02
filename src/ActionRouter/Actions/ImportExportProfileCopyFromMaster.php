<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileRepository;

class ImportExportProfileCopyFromMaster extends BaseAction {

	use Traits\SecurityAdminRequired;

	public const SLUG = 'importexport_profile_copy_from_master';

	protected function exec() {
		$success = false;
		$message = __( 'Failed to copy master configuration to sync profile.', 'wp-simple-firewall' );

		try {
			$success = ( new ProfileRepository() )->copyCurrentSiteConfigToDefaultProfile();
			if ( $success ) {
				$message = __( 'Sync profile copied from master configuration. Reloading...', 'wp-simple-firewall' );
			}
		}
		catch ( \Throwable $e ) {
		}

		$this->response()->setPayload( [
			'message'     => $message,
			'page_reload' => $success,
		] )->setPayloadSuccess( $success );
	}
}
