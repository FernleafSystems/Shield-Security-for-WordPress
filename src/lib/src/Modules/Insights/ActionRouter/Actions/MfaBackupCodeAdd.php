<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Provider\BackupCodes;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class MfaBackupCodeAdd extends MfaBase {

	public const SLUG = 'mfa_profile_backup_code_add';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		/** @var BackupCodes $provider */
		$provider = $mod->getMfaController()->getProviders()[ BackupCodes::SLUG ];

		$pass = $provider->setUser( Services::WpUsers()->getCurrentWpUser() )
						 ->resetSecret();
		$pass = implode( '-', str_split( $pass, 5 ) );

		$this->response()->action_response_data = [
			'message' => sprintf( 'Your backup login code is:<br/><code>%s</code>', $pass ),
			'code'    => $pass,
			'success' => true
		];
	}
}