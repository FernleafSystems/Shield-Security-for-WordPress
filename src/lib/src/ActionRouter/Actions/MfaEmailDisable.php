<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;

class MfaEmailDisable extends MfaUserConfigBase {

	public const SLUG = 'mfa_email_disable';

	protected function exec() {
		/** @var Options $opts */
		$opts = $this->getCon()->getModule_LoginGuard()->getOptions();
		$opts->setOpt( 'enable_email_authentication', 'N' );
		$this->response()->action_response_data = [
			'success'     => true,
			'message'     => __( '2FA by email has been disabled', 'wp-simple-firewall' ),
			'page_reload' => true
		];
	}
}