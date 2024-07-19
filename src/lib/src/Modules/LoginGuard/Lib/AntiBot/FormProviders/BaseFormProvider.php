<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\ProtectionProviders;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseFormProvider {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var string
	 */
	private $actionToAudit;

	/**
	 * @var string
	 */
	private $userToAudit;

	/**
	 * @var ProtectionProviders\BaseProtectionProvider[]
	 */
	private static $Providers = [];

	public static function SetProviders( array $providers ) {
		self::$Providers = $providers;
	}

	/**
	 * @throws \Exception
	 */
	protected function checkProviders() {
		foreach ( $this->getProtectionProviders() as $provider ) {
			$provider->performCheck( $this );
		}
	}

	/**
	 * @return ProtectionProviders\BaseProtectionProvider[]
	 */
	protected function getProtectionProviders() :array {
		return \is_array( self::$Providers ) ? self::$Providers : [];
	}

	protected function checkThenDie() {
		try {
			$this->checkProviders();
		}
		catch ( \Exception $e ) {
			Services::WpGeneral()->wpDie( $e->getMessage() );
		}
	}

	protected function run() {
		$locations = self::con()->opts->optGet( 'bot_protection_locations' );
		if ( \in_array( 'login', $locations ) ) {
			$this->login();
		}
		if ( \in_array( 'register', $locations ) ) {
			$this->register();
		}
		if ( \in_array( 'password', $locations ) ) {
			$this->lostpassword();
		}
	}

	protected function login() {
	}

	protected function register() {
	}

	protected function lostpassword() {
	}

	/**
	 * @param string $toAppend
	 */
	public function formInsertsAppend( $toAppend ) :string {
		return $toAppend.$this->buildFormInsert();
	}

	public function buildFormInsert() :string {
		return \implode( "\n", \array_map(
			function ( $provider ) {
				$provider->setAsInsertBuilt();
				return $provider->buildFormInsert( $this );
			},
			$this->getProtectionProviders()
		) );
	}

	public function printFormInsert() {
		echo $this->buildFormInsert();
	}

	public function getActionToAudit() :string {
		return empty( $this->actionToAudit ) ? 'unknown-action' : $this->actionToAudit;
	}

	public function getUserToAudit() :string {
		return empty( $this->userToAudit ) ? 'unknown' : (string)$this->userToAudit;
	}

	/**
	 * @param \WP_Error $maybeWpError
	 */
	protected function giveMeWpError( $maybeWpError ) :\WP_Error {
		return is_wp_error( $maybeWpError ) ? $maybeWpError : new \WP_Error();
	}

	/**
	 * @return $this
	 */
	protected function setActionToAudit( string $actionToAudit ) {
		$this->actionToAudit = $actionToAudit;
		return $this;
	}

	/**
	 * @param string $userToAudit
	 * @return $this
	 */
	protected function setUserToAudit( $userToAudit ) {
		$this->userToAudit = sanitize_user( $userToAudit );
		return $this;
	}
}