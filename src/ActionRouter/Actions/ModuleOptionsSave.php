<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\HandleOptionsSaveRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\EmailDeliveryVerificationDialog;

class ModuleOptionsSave extends BaseAction {

	public const SLUG = 'mod_options_save';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$con = self::con();

		$wasSecAdminEnabled = $con->comps->sec_admin->isEnabledSecAdmin();
		$wasEmailAuthEnabled = $con->opts->optIs( 'enable_email_authentication', 'Y' );

		$success = ( new HandleOptionsSaveRequest( $this->formParams() ) )->handleSave();

		$this->response()->setPayload( [
			'html'        => '',
			// Turning on Security Admin needs the PIN; turning on email 2FA opens its verification dialog on reload.
			'page_reload' => ( !$wasSecAdminEnabled && $con->comps->sec_admin->isEnabledSecAdmin() )
							 || ( !$wasEmailAuthEnabled && ( new EmailDeliveryVerificationDialog() )->isRequired() ),
			'message'     => $success ?
				sprintf( __( '%s Plugin options updated successfully.', 'wp-simple-firewall' ), $con->labels->Name )
				: sprintf( __( 'Failed to update %s plugin options.', 'wp-simple-firewall' ), $con->labels->Name )
		] )->setPayloadSuccess( $success );
	}
}
