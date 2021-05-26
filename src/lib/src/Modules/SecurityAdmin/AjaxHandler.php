<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'sec_admin_check':
				$response = $this->ajaxExec_SecAdminCheck();
				break;

			case 'sec_admin_login':
			case 'restricted_access':
				$response = $this->ajaxExec_SecAdminLogin();
				break;

			case 'req_email_remove':
				$response = $this->ajaxExec_SendEmailRemove();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_SecAdminCheck() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$secAdminCon = $mod->getSecurityAdminController();
		return [
			'time_remaining' => $secAdminCon->getSecAdminTimeRemaining(),
			'success'        => $secAdminCon->isCurrentlySecAdmin()
		];
	}

	private function ajaxExec_SecAdminLogin() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$success = $mod->getSecurityAdminController()->verifyPinRequest();
		$html = '';

		if ( $success ) {
			$msg = __( 'Security Admin PIN Accepted.', 'wp-simple-firewall' )
				   .' '.__( 'Please wait', 'wp-simple-firewall' ).' ...';
		}
		else {
			$remaining = ( new Shield\Modules\IPs\Components\QueryRemainingOffenses() )
				->setMod( $this->getCon()->getModule_IPs() )
				->setIP( Services::IP()->getRequestIp() )
				->run();
			$msg = __( 'Security Admin PIN incorrect.', 'wp-simple-firewall' ).' ';
			if ( $remaining > 0 ) {
				$msg .= sprintf( __( 'Attempts remaining: %s.', 'wp-simple-firewall' ), $remaining );
			}
			else {
				$msg .= __( "No attempts remaining.", 'wp-simple-firewall' );
			}
			$html = $mod->getSecurityAdminController()->renderPinLoginForm();
		}

		return [
			'success'     => $success,
			'page_reload' => true,
			'message'     => $msg,
			'html'        => $html,
		];
	}

	private function ajaxExec_SendEmailRemove() :array {
		( new Lib\SecurityAdmin\Ops\RemoveSecAdmin() )
			->setMod( $this->getMod() )
			->sendConfirmationEmail();
		return [
			'success' => true,
			'message' => __( 'Email sent. Please ensure the confirmation link opens in THIS browser window.' ),
		];
	}
}