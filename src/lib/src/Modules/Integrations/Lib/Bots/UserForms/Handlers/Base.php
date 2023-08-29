<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations;
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
		$opts = self::con()->getModule_LoginGuard()->getOptions();
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

	public function isEnabled() :bool {
		return parent::isEnabled() && self::con()->caps->canThirdPartyScanUsers();
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
		return sprintf( '%s-%s', static::Slug(), empty( $this->auditAction ) ? 'unknown' : $this->auditAction );
	}

	public function getAuditUser() :string {
		return empty( $this->auditUser ) ? 'unknown' : $this->auditUser;
	}

	public function getHandlerController() {
		return $this->mod()->getController_UserForms();
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
		self::con()->fireEvent(
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

	protected function fireEventBlockLogin() {
		self::con()->fireEvent( 'login_block' );
	}

	protected function fireEventBlockRegister() {
		self::con()->fireEvent( 'block_register' );
	}

	protected function fireEventBlockLostpassword() {
		self::con()->fireEvent( 'block_lostpassword' );
	}

	protected function fireEventBlockCheckout() {
		self::con()->fireEvent( 'block_checkout' );
	}

	protected function isBotBlockEnabled() :bool {
		/** @var LoginGuard\Options $loginOpts */
		$loginOpts = self::con()->getModule_LoginGuard()->getOptions();
		return $loginOpts->isEnabledAntiBot();
	}

	protected function getErrorMessage() :string {
		return sprintf( __( '%s Bot Check Failed.', 'wp-simple-firewall' ), self::con()->getHumanName() );
	}
}