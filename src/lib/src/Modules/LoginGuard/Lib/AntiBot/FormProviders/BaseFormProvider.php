<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\AntiBot\FormProviders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard;
use FernleafSystems\Wordpress\Services\Services;

abstract class BaseFormProvider extends ExecOnceModConsumer {

	/**
	 * @var string
	 */
	private $sActionToAudit;

	/**
	 * @var string
	 */
	private $sUserToAudit;

	/**
	 * @var LoginGuard\Lib\AntiBot\ProtectionProviders\BaseProtectionProvider[]
	 */
	private static $aProtectionProviders;

	public static function SetProviders( array $providers ) {
		self::$aProtectionProviders = $providers;
	}

	/**
	 * @throws \Exception
	 */
	protected function checkProviders() {
		foreach ( $this->getProtectionProviders() as $provider ) {
			$provider->performCheck( $this );
		}
	}

	protected function getProtectionProviders() :array {
		return is_array( self::$aProtectionProviders ) ? self::$aProtectionProviders : [];
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
		/** @var LoginGuard\Options $opts */
		$opts = $this->getOptions();
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

	/**
	 * @param string $sToAppend
	 * @return string
	 */
	public function formInsertsAppend( $sToAppend ) {
		return $sToAppend.$this->buildFormInsert();
	}

	public function buildFormInsert() :string {
		$aInserts = [];
		if ( is_array( self::$aProtectionProviders ) ) {
			foreach ( self::$aProtectionProviders as $oProvider ) {
				$aInserts[] = $oProvider->buildFormInsert( $this );
				$oProvider->setAsInsertBuilt();
			}
		}
		return implode( "\n", $aInserts );
	}

	public function printFormInsert() {
		echo $this->buildFormInsert();
	}

	/**
	 * @return string
	 */
	public function getActionToAudit() {
		return empty( $this->sActionToAudit ) ? 'unknown-action' : $this->sActionToAudit;
	}

	/**
	 * @return string
	 */
	public function getUserToAudit() {
		return empty( $this->sUserToAudit ) ? 'unknown' : $this->sUserToAudit;
	}

	/**
	 * @param \WP_Error $oMaybeWpError
	 * @return \WP_Error
	 */
	protected function giveMeWpError( $oMaybeWpError ) {
		return is_wp_error( $oMaybeWpError ) ? $oMaybeWpError : new \WP_Error();
	}

	/**
	 * @param string $sActionToAudit
	 * @return $this
	 */
	protected function setActionToAudit( $sActionToAudit ) {
		$this->sActionToAudit = $sActionToAudit;
		return $this;
	}

	/**
	 * @param string $sUserToAudit
	 * @return $this
	 */
	protected function setUserToAudit( $sUserToAudit ) {
		$this->sUserToAudit = sanitize_user( $sUserToAudit );
		return $this;
	}
}