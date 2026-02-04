<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;

class MfaBackupCodeDelete extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_backup_code_delete';

	protected function exec() {
		$available = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() );
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

		self::con()->admin_notices->addFlash( $msg, $this->getActiveWPUser(), !$success );

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}