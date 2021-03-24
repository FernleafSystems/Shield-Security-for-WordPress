<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'sec_admin_check':
				$aResponse = $this->ajaxExec_SecAdminCheck();
				break;

			case 'sec_admin_login':
			case 'restricted_access':
				$aResponse = $this->ajaxExec_SecAdminLogin();
				break;

			case 'sec_admin_login_box':
				$aResponse = $this->ajaxExec_SecAdminLoginBox();
				break;

			case 'req_email_remove':
				$aResponse = $this->ajaxExec_SendEmailRemove();
				break;

			default:
				$aResponse = parent::processAjaxAction( $action );
		}

		return $aResponse;
	}

	private function ajaxExec_SecAdminCheck() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return [
			'timeleft' => $mod->getSecAdminTimeLeft(),
			'success'  => $mod->isSecAdminSessionValid()
		];
	}

	private function ajaxExec_SecAdminLogin() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$success = false;
		$html = '';

		if ( $mod->testSecAccessKeyRequest() ) {

			if ( $mod->setSecurityAdminStatusOnOff( true ) ) {
				$success = true;
				$msg = __( 'Security Admin PIN Accepted.', 'wp-simple-firewall' )
					   .' '.__( 'Please wait', 'wp-simple-firewall' ).' ...';
			}
			else {
				$msg = __( 'Failed to process key - you may need to re-login to WordPress.', 'wp-simple-firewall' );
			}
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
			$html = $this->renderAdminAccessAjaxLoginForm( $msg );
		}

		return [
			'success'     => $success,
			'page_reload' => true,
			'message'     => $msg,
			'html'        => $html,
		];
	}

	private function ajaxExec_SecAdminLoginBox() :array {
		return [
			'success' => true,
			'html'    => $this->renderAdminAccessAjaxLoginForm()
		];
	}

	private function ajaxExec_SendEmailRemove() :array {
		( new Shield\Modules\SecurityAdmin\Lib\Actions\RemoveSecAdmin() )
			->setMod( $this->getMod() )
			->sendConfirmationEmail();
		return [
			'success' => true,
			'message' => __( 'Email sent. Please ensure the confirmation link opens in THIS browser window.' ),
		];
	}

	private function renderAdminAccessAjaxLoginForm( string $msg = '' ) :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->renderTemplate( 'snippets/admin_access_login', [
			'ajax'    => [
				'sec_admin_login' => json_encode( $mod->getSecAdminLoginAjaxData() )
			],
			'strings' => [
				'access_message' => empty( $msg ) ? __( 'Enter your Security Admin PIN', 'wp-simple-firewall' ) : $msg
			]
		] );
	}
}