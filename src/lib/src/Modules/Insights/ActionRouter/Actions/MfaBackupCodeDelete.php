<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaBackupCodeDelete extends MfaBase {

	const SLUG = 'mfa_profile_backup_code_delete';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		/** @var BackupCodes $provider */
		$provider = $mod->getMfaController()->getProviders()[ BackupCodes::SLUG ];
		$provider->setUser( Services::WpUsers()->getCurrentWpUser() )->remove();
		$mod->setFlashAdminNotice(
			__( 'Multi-factor login backup code has been removed from your profile', 'wp-simple-firewall' )
		);

		$this->response()->action_response_data = [
			'message' => __( 'Your backup login codes have been deleted.', 'wp-simple-firewall' ),
			'success' => true
		];
	}
}