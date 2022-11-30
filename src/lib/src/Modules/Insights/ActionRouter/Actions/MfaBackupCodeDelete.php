<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaBackupCodeDelete extends MfaBase {

	public const SLUG = 'mfa_profile_backup_code_delete';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;

		$available = $mod->getMfaController()->getProvidersAvailableToUser( Services::WpUsers()->getCurrentWpUser() );
		/** @var ?BackupCodes $provider */
		$provider = $available[ BackupCodes::ProviderSlug() ] ?? null;
		if ( empty( $provider ) ) {
			$msg = __( 'This action is unavailable for your profile at this time.', 'wp-simple-firewall' );
			$success = false;
		}
		else {
			$provider->removeFromProfile();
			$msg = __( 'Login backup codes have been removed from your profile', 'wp-simple-firewall' );
			$success = true;
		}
		$mod->setFlashAdminNotice( $msg, Services::WpUsers()->getCurrentWpUser(), !$success );

		$this->response()->action_response_data = [
			'message' => $msg,
			'success' => $success
		];
	}
}