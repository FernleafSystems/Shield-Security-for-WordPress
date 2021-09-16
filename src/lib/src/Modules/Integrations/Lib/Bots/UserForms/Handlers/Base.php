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

	private static $isBot = null;

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

	/**
	 * @param string $action
	 * @return $this
	 */
	protected function setAuditAction( string $action ) {
		$this->auditAction = $action;
		return $this;
	}

	/**
	 * @param string $user
	 * @return $this
	 */
	protected function setAuditUser( string $user ) {
		$this->auditUser = sanitize_user( $user );
		return $this;
	}

	public function checkIsBot() :bool {
		if ( is_null( self::$isBot ) ) {
			self::$isBot = $this->isBot();
			$this->getCon()->fireEvent(
				sprintf( 'user_form_bot_%s', self::$isBot ? 'fail' : 'pass' ),
				[
					'audit_params' => [
						'form_provider' => $this->getProviderName(),
						'action'        => $this->getAuditAction(),
						'username'      => $this->getAuditUser(),
					]
				]
			);
		}
		return self::$isBot;
	}

	public function isEnabled() :bool {
		/** @var Integrations\Options $opts */
		$opts = $this->getOptions();
		return in_array( $this->getHandlerSlug(), $opts->getUserFormProviders() );
	}

	protected function getErrorMessage() :string {
		return sprintf( __( '%s Bot Check Failed.', 'wp-simple-firewall' ), $this->getCon()->getHumanName() );
	}
}