<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;

abstract class Base extends Integrations\Lib\Bots\Common\BaseHandler {

	/**
	 * @var string
	 */
	private $auditAction;

	/**
	 * @var string
	 */
	private $auditUser;

	protected function run() {
		/** @var LoginGuard\Options $opts */
		$opts = $this->getCon()->getModule_LoginGuard()->getOptions();
		if ( $opts->isProtectLogin() ) {
			$this->login();
		}
		if ( $opts->isProtectRegister() ) {
			$this->register();
		}
		if ( $opts->isProtectLostPassword() ) {
			$this->lostpassword();
		}
		$this->checkout();
	}

	protected function login() {
	}

	protected function register() {
	}

	protected function lostpassword() {
	}

	protected function checkout() {
	}

	public function getAuditAction() :string {
		return sprintf( '%s-%s', $this->getHandlerSlug(), empty( $this->auditAction ) ? 'unknown' : $this->auditAction );
	}

	public function getAuditUser() :string {
		return empty( $this->auditUser ) ? 'unknown' : $this->auditUser;
	}

	public function getHandlerController() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		return $mod->getController_UserForms();
	}

	/**
	 * @return $this
	 */
	protected function setAuditAction( string $action ) {
		$this->auditAction = $action;
		return $this;
	}

	/**
	 * @return $this
	 */
	protected function setAuditUser( string $user ) {
		$this->auditUser = sanitize_user( $user );
		return $this;
	}

	protected function fireBotEvent() {
		$this->getCon()->fireEvent(
			sprintf( 'user_form_bot_%s', $this->isBot() ? 'fail' : 'pass' ),
			[
				'audit_params' => [
					'form_provider' => $this->getHandlerName(),
					'action'        => $this->getAuditAction(),
					'username'      => $this->getAuditUser(),
				]
			]
		);
	}

	protected function isBotBlockEnabled() :bool {
		/** @var LoginGuard\Options $loginOpts */
		$loginOpts = $this->getCon()->getModule_LoginGuard()->getOptions();
		return $loginOpts->isEnabledAntiBot();
	}

	protected function getErrorMessage() :string {
		return sprintf( __( '%s Bot Check Failed.', 'wp-simple-firewall' ), $this->getCon()->getHumanName() );
	}
}