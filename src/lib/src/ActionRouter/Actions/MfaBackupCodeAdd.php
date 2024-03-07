<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;

class MfaBackupCodeAdd extends MfaUserConfigBase {

	public const SLUG = 'mfa_profile_backup_code_add';

	protected function exec() {
		$available = self::con()->comps->mfa->getProvidersAvailableToUser( $this->getActiveWPUser() );
		/** @var ?BackupCodes $provider */
		$provider = $available[ BackupCodes::ProviderSlug() ] ?? null;
		if ( empty( $provider ) ) {
			$msg = __( "Changing Backup Code options isn't currently available to you.", 'wp-simple-firewall' );
			$success = false;
		}
		else {
			$pass = \implode( '-', \str_split( $provider->resetSecret(), 5 ) );
			$msg = sprintf( 'Your backup login code is: %s', $pass );
			$success = true;
		}

		$this->response()->action_response_data = [
			'message' => $msg,
			'code'    => $pass ?? '',
			'success' => $success
		];
	}
}