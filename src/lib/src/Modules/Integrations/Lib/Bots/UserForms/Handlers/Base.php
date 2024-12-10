<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\UserForms\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\AntiBot\CoolDownHandler;

abstract class Base extends \FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Common\BaseHandler {

	private ?string $auditAction = null;

	private ?string $auditUser = null;

	protected function run() {
		$opts = self::con()->comps->opts_lookup;
		if ( $opts->enabledLoginProtectionArea( 'login' ) ) {
			$this->login();
		}
		if ( $opts->enabledLoginProtectionArea( 'register' ) ) {
			$this->register();
		}
		if ( $opts->enabledLoginProtectionArea( 'password' ) ) {
			$this->lostpassword();
		}
		$this->checkout();
	}

	protected function getCooldownContext() :string {
		return CoolDownHandler::CONTEXT_AUTH;
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
		return self::con()->comps->forms_users;
	}

	protected function setAuditAction( string $action ) :self {
		$this->auditAction = $action;
		return $this;
	}

	protected function setAuditUser( string $user ) :self {
		$this->auditUser = sanitize_user( $user );
		return $this;
	}

	protected function fireBotEvent() {
		self::con()->comps->events->fireEvent(
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
		self::con()->comps->events->fireEvent( 'login_block' );
	}

	protected function fireEventBlockRegister() {
		self::con()->comps->events->fireEvent( 'block_register' );
	}

	protected function fireEventBlockLostpassword() {
		self::con()->comps->events->fireEvent( 'block_lostpassword' );
	}

	protected function fireEventBlockCheckout() {
		self::con()->comps->events->fireEvent( 'block_checkout' );
	}

	protected function getErrorMessage() :string {
		return $this->isCoolDownBlockRequired() ?
			\implode( ' ', [
				__( 'Cooldown in effect.', 'wp-simple-firewall' ),
				sprintf( __( 'Please wait %s seconds before attempting this action again.', 'wp-simple-firewall' ), self::con()->comps->cool_down->cooldownRemaining( $this->getCooldownContext() ) )
			] )
			: sprintf( __( '%s Bot Check Failed.', 'wp-simple-firewall' ), self::con()->labels->Name );
	}
}