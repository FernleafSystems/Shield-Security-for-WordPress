<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;

class MfaBackupCodeDelete extends MfaBase {

	public const SLUG = 'mfa_profile_backup_code_delete';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;

		$user = $this->getActiveWPUser();
		$available = $mod->getMfaController()->getProvidersAvailableToUser( $user );
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

		$this->getCon()
			 ->getAdminNotices()
			 ->addFlash( $msg, $user, !$success );

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}