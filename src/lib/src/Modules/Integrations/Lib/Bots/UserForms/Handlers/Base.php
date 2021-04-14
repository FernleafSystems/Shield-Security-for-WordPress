<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Options;

abstract class Base extends BaseHandler {

	/**
	 * @var string
	 */
	private $actionToAudit;

	/**
	 * @var string
	 */
	private $userToAudit;

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
	}

	protected function login() {
	}

	protected function register() {
	}

	protected function lostpassword() {
	}

	public function getActionToAudit() :string {
		return empty( $this->actionToAudit ) ? 'unknown-action' : $this->actionToAudit;
	}

	public function getUserToAudit() :string {
		return empty( $this->userToAudit ) ? 'unknown' : $this->userToAudit;
	}

	/**
	 * @param string $action
	 * @return $this
	 */
	protected function setActionToAudit( string $action ) {
		$this->actionToAudit = $action;
		return $this;
	}

	/**
	 * @param string $user
	 * @return $this
	 */
	protected function setUserToAudit( string $user ) {
		$this->userToAudit = sanitize_user( $user );
		return $this;
	}

	public function checkIsBot() :bool {
		$isBot = $this->isBot();
		$this->getCon()->fireEvent(
			sprintf( 'user_form_%s', $isBot ? 'fail' : 'pass' ),
			[
				'audit' => [
					'form_provider' => $this->getProviderName(),
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