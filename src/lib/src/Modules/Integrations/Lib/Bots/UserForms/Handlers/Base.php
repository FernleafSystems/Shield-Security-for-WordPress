<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;

abstract class Base extends BaseHandler {

	/**
	 * @var string
	 */
	private $auditAction;

	/**
	 * @var string
	 */
	private $auditUser;

	protected function run() {
		/** @var Options $opts */
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
		return empty( $this->auditAction ) ? 'unknown-action' : $this->auditAction;
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
		$isBot = $this->isBot();
		$this->getCon()->fireEvent(
			sprintf( 'user_form_bot_%s', $isBot ? 'fail' : 'pass' ),
			[
				'audit' => [
					'form_provider' => $this->getProviderName(),
					'action'        => $this->getAuditAction(),
					'username'      => $this->getAuditUser(),
				]
			]
		);
		return $isBot;
	}

	protected function isEnabled() :bool {
		return in_array( $this->getHandlerSlug(), $this->getOptions()->getOpt( 'user_form_providers', [] ) );
	}

	protected function getErrorMessage() :string {
		return sprintf( __( 'Failed %s Bot Check', 'wp-simple-firewall' ), $this->getCon()->getHumanName() );
	}
}